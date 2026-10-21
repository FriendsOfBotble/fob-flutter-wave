<?php

namespace FriendsOfBotble\FlutterWave\Services;

use FriendsOfBotble\FlutterWave\Providers\FlutterWaveServiceProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class FlutterWaveService
{
    protected string $publicKey;

    protected string $secretKey;

    protected string $encryptionKey;

    protected string $baseUrl = 'https://checkout.flutterwave.com/v3';

    protected string $baseApiUrl = 'https://api.flutterwave.com/v3';

    protected array $data = [];

    public function __construct()
    {
        $this->publicKey = get_payment_setting('public_key', FlutterWaveServiceProvider::MODULE_NAME);
        $this->secretKey = get_payment_setting('secret_key', FlutterWaveServiceProvider::MODULE_NAME);
        $this->encryptionKey = get_payment_setting('encryption_key', FlutterWaveServiceProvider::MODULE_NAME);
    }

    public function withData(array $data): self
    {
        $this->data = $data;

        $this->withAdditionalData();

        return $this;
    }

    public function redirectToCheckoutPage(): void
    {
        echo view('plugins/flutter-wave::form', [
            'data' => $this->data,
            'action' => $this->getPaymentUrl(),
        ]);

        exit();
    }

    protected function getPaymentUrl(): string
    {
        return $this->baseUrl . '/hosted/pay';
    }

    protected function withAdditionalData(): void
    {
        $this->data = array_merge($this->data, [
            'key' => $this->getPublicKey(),
        ]);
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getWebhookSecretHash(): ?string
    {
        return get_payment_setting('webhook_secret_hash', FlutterWaveServiceProvider::MODULE_NAME) ?: null;
    }

    public static function formatAmount(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * The checkout form is posted from the browser and can be edited, so the expected payment is
     * signed with the app key and checked against the transaction returned by the verify API.
     */
    public static function signCheckout(array $checkout): string
    {
        return hash_hmac('sha256', implode('|', [
            (string) Arr::get($checkout, 'tx_ref'),
            static::formatAmount(Arr::get($checkout, 'amount', 0)),
            strtoupper((string) Arr::get($checkout, 'currency')),
            (string) Arr::get($checkout, 'order_id'),
            (string) Arr::get($checkout, 'customer_id'),
            (string) Arr::get($checkout, 'customer_type'),
            (string) Arr::get($checkout, 'token'),
        ]), (string) config('app.key'));
    }

    /**
     * Returns the signed checkout carried in the meta of a verified transaction, or null when the
     * signature is missing or doesn't match.
     */
    public static function verifiedCheckout(array $transaction): ?array
    {
        $meta = Arr::get($transaction, 'meta') ?: [];

        // Flutterwave returns meta either as an object or as a list of {metaname, metavalue}.
        if (array_is_list($meta)) {
            $meta = collect($meta)
                ->filter(fn ($item) => is_array($item) && isset($item['metaname']))
                ->mapWithKeys(fn ($item) => [$item['metaname'] => $item['metavalue'] ?? null])
                ->all();
        }

        $checkout = [
            'tx_ref' => (string) Arr::get($transaction, 'tx_ref'),
            'amount' => (string) Arr::get($meta, 'amount'),
            'currency' => (string) Arr::get($meta, 'currency'),
            'order_id' => (string) Arr::get($meta, 'order_id'),
            'customer_id' => (string) Arr::get($meta, 'customer_id'),
            'customer_type' => (string) Arr::get($meta, 'customer_type'),
            'token' => (string) Arr::get($meta, 'token'),
        ];

        $signature = (string) Arr::get($meta, 'sig');

        if (! $signature || $checkout['amount'] === '' || ! hash_equals(static::signCheckout($checkout), $signature)) {
            return null;
        }

        return [
            ...$checkout,
            'amount' => (float) $checkout['amount'],
            'currency' => strtoupper($checkout['currency']),
            'order_id' => json_decode($checkout['order_id'], true),
        ];
    }

    public function queryTransaction(string $referenceNumber): array
    {
        if (! ctype_digit($referenceNumber)) {
            return [
                'error' => true,
                'message' => 'Invalid transaction.',
            ];
        }

        $response = Http::asJson()
            ->withToken($this->getSecretKey())
            ->get($this->baseApiUrl . '/transactions/' . $referenceNumber . '/verify');

        if (! $response->ok()) {
            return [
                'error' => true,
                'message' => $response->reason(),
            ];
        }

        return $response->json() ?: ['error' => true, 'message' => 'Invalid response.'];
    }

    public function refundOrder(string $chargeId, float $amount): array
    {
        $response = Http::asJson()
            ->withToken($this->getSecretKey())
            ->post($this->baseApiUrl . '/transactions/' . $chargeId . '/refund', [
                'amount' => $amount,
            ]);

        if ($response->status() == 400) {
            return [
                'error' => true,
                'message' => __('Refunds are not enabled on accounts by default. You would need to request this feature on your account by sending an email to hi@flutterwavego.com. This warning also applies to other refund-related features, i.e. getting the details of a refunded transaction and querying all refunds.'),
            ];
        }

        return [
            'error' => $response->failed(),
            'message' => $response->json('message'),
        ];
    }
}
