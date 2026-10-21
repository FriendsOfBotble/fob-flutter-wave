<?php

namespace FriendsOfBotble\FlutterWave\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Hotel\Models\Booking;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Models\Payment;
use Botble\Payment\Supports\PaymentHelper;
use FriendsOfBotble\FlutterWave\Providers\FlutterWaveServiceProvider;
use FriendsOfBotble\FlutterWave\Services\FlutterWaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class FlutterWaveController extends BaseController
{
    public function callback(
        Request $request,
        BaseHttpResponse $response,
        FlutterWaveService $flutterWaveService
    ): BaseHttpResponse {
        if (! $request->input('transaction_id')) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL());
        }

        $result = $flutterWaveService->queryTransaction((string) $request->input('transaction_id'));

        if (! empty($result['error']) || empty($result['data'])) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL())
                ->setMessage($result['message'] ?? null);
        }

        $data = $result['data'];
        $checkout = FlutterWaveService::verifiedCheckout($data);

        if (! $checkout) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL())
                ->setMessage(__('Checkout failed!'));
        }

        // Bank transfer, USSD and mobile money can stay pending for a while, the webhook completes them.
        if (in_array(Arr::get($data, 'status'), ['pending', 'processing'], true)) {
            return $response
                ->setNextUrl(PaymentHelper::getRedirectURL($checkout['token']))
                ->setMessage(__('Your payment is being processed.'));
        }

        try {
            $completed = $this->processVerifiedTransaction($data, $checkout, $request);
        } catch (LockTimeoutException) {
            return $response
                ->setNextUrl(PaymentHelper::getRedirectURL($checkout['token']))
                ->setMessage(__('Your payment is being processed.'));
        }

        if (! $completed) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL($checkout['token']))
                ->setMessage(__('Checkout failed!'));
        }

        if (is_plugin_active('hotel')) {
            $booking = Booking::query()
                ->select('transaction_id')
                ->find(Arr::first(Arr::wrap($checkout['order_id'])));

            if (! $booking) {
                return $response
                    ->setNextUrl(PaymentHelper::getCancelURL())
                    ->setMessage(__('Checkout failed!'));
            }

            return $response
                ->setNextUrl(PaymentHelper::getRedirectURL($booking->transaction_id))
                ->setMessage(__('Checkout successfully!'));
        }

        $nextUrl = PaymentHelper::getRedirectURL($checkout['token']);

        if (is_plugin_active('job-board') || is_plugin_active('real-estate')) {
            $nextUrl = $nextUrl . '?charge_id=' . $data['id'];
        }

        return $response
            ->setNextUrl($nextUrl)
            ->setMessage(__('Checkout successfully!'));
    }

    public function webhook(Request $request, FlutterWaveService $flutterWaveService)
    {
        $secretHash = $flutterWaveService->getWebhookSecretHash();
        $signature = (string) $request->header('verif-hash');

        if (! $secretHash || ! $signature || ! hash_equals($secretHash, $signature)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        $transactionId = Arr::get($payload, 'data.id');

        if (! $transactionId) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        // Never trust the webhook body itself, fetch the transaction from Flutterwave.
        $result = $flutterWaveService->queryTransaction((string) $transactionId);

        if (! empty($result['error']) || empty($result['data'])) {
            return response()->json(['message' => 'Unable to verify transaction'], 400);
        }

        $checkout = FlutterWaveService::verifiedCheckout($result['data']);

        if (! $checkout) {
            return response()->json(['message' => 'Unknown checkout'], 400);
        }

        try {
            $this->processVerifiedTransaction($result['data'], $checkout, $request);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Busy, retry later'], 409);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Completes the payment from a transaction returned by the verify API, matched against the
     * signed checkout. Returns true when the payment is (or already was) completed.
     */
    protected function processVerifiedTransaction(array $data, array $checkout, Request $request): bool
    {
        if (
            Arr::get($data, 'status') !== 'successful'
            || strtoupper((string) Arr::get($data, 'currency')) !== $checkout['currency']
            || round((float) Arr::get($data, 'amount'), 2) < round($checkout['amount'], 2)
        ) {
            return false;
        }

        $chargeId = (string) $data['id'];

        // The callback and the webhook can arrive together, only process the payment once.
        return Cache::lock('flutter-wave.payment.' . $chargeId, 10)->block(5, function () use ($data, $checkout, $chargeId, $request): bool {
            $alreadyCompleted = Payment::query()
                ->where('charge_id', $chargeId)
                ->where('status', PaymentStatusEnum::COMPLETED)
                ->exists();

            if ($alreadyCompleted) {
                return true;
            }

            do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
                'order_id' => $checkout['order_id'],
                'amount' => $checkout['amount'],
                'currency' => $checkout['currency'],
                'charge_id' => $chargeId,
                'payment_channel' => FlutterWaveServiceProvider::MODULE_NAME,
                'status' => PaymentStatusEnum::COMPLETED,
                'customer_id' => $checkout['customer_id'] ?: null,
                'customer_type' => $checkout['customer_type'] ?: null,
                'payment_type' => 'direct',
            ], $request);

            return true;
        });
    }
}
