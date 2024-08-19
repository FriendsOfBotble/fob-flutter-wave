<?php

namespace FriendsOfBotble\FlutterWave\Providers;

use Botble\Ecommerce\Facades\OrderHelper;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Payment\Facades\PaymentMethods;
use FriendsOfBotble\FlutterWave\Services\FlutterWavePaymentService;
use FriendsOfBotble\FlutterWave\Services\FlutterWaveService;
use Html;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_filter(PAYMENT_METHODS_SETTINGS_PAGE, function (?string $settings) {
            $name = 'FlutterWave';
            $moduleName = FlutterWaveServiceProvider::MODULE_NAME;
            $status = (bool) get_payment_setting('status', $moduleName);

            return $settings . view('plugins/flutter-wave::settings', compact('name', 'moduleName', 'status'))->render(
            );
        }, 999);

        add_filter(BASE_FILTER_ENUM_ARRAY, function (array $values, string $class): array {
            if ($class === PaymentMethodEnum::class) {
                $values['flutter_wave'] = FlutterWaveServiceProvider::MODULE_NAME;
            }

            return $values;
        }, 999, 2);

        add_filter(BASE_FILTER_ENUM_LABEL, function ($value, $class): string {
            if ($class === PaymentMethodEnum::class && $value === FlutterWaveServiceProvider::MODULE_NAME) {
                $value = 'Flutter Wave';
            }

            return $value;
        }, 999, 2);

        add_filter(BASE_FILTER_ENUM_HTML, function (string $value, string $class): string {
            if ($class === PaymentMethodEnum::class && $value === FlutterWaveServiceProvider::MODULE_NAME) {
                $value = Html::tag(
                    'span',
                    PaymentMethodEnum::getLabel($value),
                    ['class' => 'label-success status-label']
                )->toHtml();
            }

            return $value;
        }, 999, 2);

        add_filter(PAYMENT_FILTER_ADDITIONAL_PAYMENT_METHODS, function (?string $html, array $data): ?string {
            if (get_payment_setting('status', FlutterWaveServiceProvider::MODULE_NAME)) {
                $payFlutterWave = new FlutterWaveService();

                if (! $payFlutterWave->getPublicKey() || ! $payFlutterWave->getSecretKey()) {
                    return $html;
                }

                PaymentMethods::method(FlutterWaveServiceProvider::MODULE_NAME, [
                    'html' => view(
                        'plugins/flutter-wave::methods',
                        $data,
                        ['moduleName' => FlutterWaveServiceProvider::MODULE_NAME]
                    )->render(),
                ]);
            }

            return $html;
        }, 999, 2);

        add_filter(PAYMENT_FILTER_PAYMENT_INFO_DETAIL, function ($data, $payment) {
            if ($payment->payment_channel == FlutterWaveServiceProvider::MODULE_NAME) {
                $paymentDetail = (new FlutterWavePaymentService())->getPaymentDetails($payment->charge_id);

                $data = view('plugins/flutter-wave::detail', ['payment' => $paymentDetail])->render();
            }

            return $data;
        }, 1, 2);

        add_filter(PAYMENT_FILTER_GET_SERVICE_CLASS, function (?string $data, string $value): ?string {
            if ($value === FlutterWaveServiceProvider::MODULE_NAME) {
                $data = FlutterWavePaymentService::class;
            }

            return $data;
        }, 20, 2);

        add_filter(PAYMENT_FILTER_AFTER_POST_CHECKOUT, function (array $data, Request $request): array {
            if ($data['type'] !== FlutterWaveServiceProvider::MODULE_NAME) {
                return $data;
            }

            $paymentData = apply_filters(PAYMENT_FILTER_PAYMENT_DATA, [], $request);

            try {
                $payFlutterWave = new FlutterWaveService();

                $data = [
                    'public_key' => $payFlutterWave->getPublicKey(),
                    'redirect_url' => route('payment.flutter-wave.callback'),
                    'currency' => $data['currency'],
                    'amount' => $data['amount'],
                    'customer[email]' => $paymentData['address']['email'],
                    'customer[name]' => $paymentData['address']['name'],
                    'meta[customer_id]' => $paymentData['customer_id'],
                    'meta[customer_type]' => $paymentData['customer_type'],
                    'meta[order_id]' => json_encode($paymentData['order_id']),
                ];

                if (is_plugin_active('ecommerce')) {
                    $data['tx_ref'] = OrderHelper::getOrderSessionToken() . '-' . time();
                    $data['meta[token]'] = OrderHelper::getOrderSessionToken();
                } else {
                    $tokenGenerate = session('subscribed_packaged_id');
                    $data['tx_ref'] = $tokenGenerate . '-' . time();
                    $data['meta[token]'] = $tokenGenerate;
                }

                $payFlutterWave->withData($data);

                $payFlutterWave->redirectToCheckoutPage();
            } catch (Throwable $exception) {
                $data['error'] = true;
                $data['message'] = json_encode($exception->getMessage());
            }

            return $data;
        }, 999, 2);
    }
}
