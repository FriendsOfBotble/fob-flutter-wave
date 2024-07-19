<?php

namespace FriendsOfBotble\FlutterWave\Services;

use FriendsOfBotble\FlutterWave\Providers\FlutterWaveServiceProvider;
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

    public function queryTransaction(string $referenceNumber)
    {
        $response = Http::asJson()
            ->withToken($this->getSecretKey())
            ->withoutVerifying()
            ->get($this->baseApiUrl . '/transactions/' . $referenceNumber . '/verify');

        if (! $response->ok()) {
            return [
                'error' => false,
                'message' => $response->reason(),
            ];
        }

        return $response->json();
    }

    public function refundOrder(string $chargeId, float $amount): array
    {
        $response = Http::asJson()
            ->withToken($this->getSecretKey())
            ->withoutVerifying()
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
