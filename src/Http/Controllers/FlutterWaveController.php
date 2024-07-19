<?php

namespace FriendsOfBotble\FlutterWave\Http\Controllers;

use FriendsOfBotble\FlutterWave\Providers\FlutterWaveServiceProvider;
use FriendsOfBotble\FlutterWave\Services\FlutterWaveService;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Ecommerce\Models\Customer;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Supports\PaymentHelper;
use Illuminate\Http\Request;

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

        $result = $flutterWaveService->queryTransaction($request->input('transaction_id'));

        if (! $result) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL());
        }

        $data = $result['data'];

        switch ($data['status']) {
            case 'successful':
                $status = PaymentStatusEnum::COMPLETED;

                break;

            case 'failure':
                $status = PaymentStatusEnum::FAILED;

                break;

            default:
                $status = PaymentStatusEnum::PENDING;

                break;
        }

        if ($status === PaymentStatusEnum::FAILED) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL())
                ->setMessage($request->input('error_message'));
        }

        do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
            'order_id' => json_decode($data['meta']['order_id']),
            'amount' => $data['amount'],
            'charge_id' => $data['id'],
            'payment_channel' => FlutterWaveServiceProvider::MODULE_NAME,
            'status' => $status,
            'customer_id' => $data['meta']['customer_id'],
            'customer_type' => $data['meta']['customer_type'],
            'payment_type' => 'direct',
        ], $request);

        return $response
            ->setNextUrl(PaymentHelper::getRedirectURL($data['meta']['token']))
            ->setMessage(__('Checkout successfully!'));
    }

    public function webhook(Request $request)
    {
        $data = json_decode($request->input());
        if (! $data) {
            return;
        }

        $status = match ($data['status']) {
            'successful' => PaymentStatusEnum::COMPLETED,
            'failure' => PaymentStatusEnum::FAILED,
            default => PaymentStatusEnum::PENDING,
        };

        if ($status === PaymentStatusEnum::FAILED) {
            return;
        }

        do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
            'order_id' => json_decode($data['meta']['order_id']),
            'amount' => $data['amount'],
            'charge_id' => $data['id'],
            'payment_channel' => FlutterWaveServiceProvider::MODULE_NAME,
            'status' => $status,
            'customer_id' => $data['customer']['id'],
            'customer_type' => Customer::class,
            'payment_type' => 'direct',
        ], $request);
    }
}
