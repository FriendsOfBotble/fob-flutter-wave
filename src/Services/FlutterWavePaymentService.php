<?php

namespace FriendsOfBotble\FlutterWave\Services;

class FlutterWavePaymentService
{
    public function getSupportRefundOnline(): bool
    {
        return true;
    }

    public function refundOrder(string $paymentId, float $amount)
    {
        return (new FlutterWaveService())->refundOrder($paymentId, $amount);
    }

    public function getPaymentDetails(string $paymentId)
    {
        return (new FlutterWaveService())->queryTransaction($paymentId);
    }
}
