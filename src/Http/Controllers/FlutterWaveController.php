<?php

namespace FriendsOfBotble\FlutterWave\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Hotel\Models\Booking;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Supports\PaymentHelper;
use FriendsOfBotble\FlutterWave\Providers\FlutterWaveServiceProvider;
use FriendsOfBotble\FlutterWave\Services\FlutterWaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
        $checkout = $this->processVerifiedTransaction($data, $request);

        if (! $checkout) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL())
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

        $this->processVerifiedTransaction($result['data'], $request);

        return response()->json(['success' => true]);
    }

    /**
     * Completes the payment from a transaction returned by the verify API, matched against the
     * checkout stored server-side. Returns the checkout when the payment is (or already was) completed.
     */
    protected function processVerifiedTransaction(array $data, Request $request): ?array
    {
        if (Arr::get($data, 'status') !== 'successful' || ! ($txRef = Arr::get($data, 'tx_ref'))) {
            return null;
        }

        $cacheKey = FlutterWaveService::checkoutCacheKey($txRef);
        $processedKey = $cacheKey . '.processed';

        return Cache::lock($cacheKey . '.lock', 10)->block(5, function () use ($data, $request, $cacheKey, $processedKey) {
            if ($checkout = Cache::get($processedKey)) {
                return $checkout;
            }

            $checkout = Cache::get($cacheKey);

            if (
                ! $checkout
                || strtoupper((string) Arr::get($data, 'currency')) !== $checkout['currency']
                || (float) Arr::get($data, 'amount') < $checkout['amount']
            ) {
                return null;
            }

            do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
                'order_id' => $checkout['order_id'],
                'amount' => $checkout['amount'],
                'currency' => $checkout['currency'],
                'charge_id' => $data['id'],
                'payment_channel' => FlutterWaveServiceProvider::MODULE_NAME,
                'status' => PaymentStatusEnum::COMPLETED,
                'customer_id' => $checkout['customer_id'],
                'customer_type' => $checkout['customer_type'],
                'payment_type' => 'direct',
            ], $request);

            Cache::forget($cacheKey);
            Cache::put($processedKey, $checkout, now()->addDay());

            return $checkout;
        });
    }
}
