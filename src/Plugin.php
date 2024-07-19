<?php

namespace FriendsOfBotble\FlutterWave;

use Botble\PluginManagement\Abstracts\PluginOperationAbstract;
use Botble\Setting\Models\Setting;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Setting::query()
            ->whereIn('key', [
                'payment_flutter_wave_name',
                'payment_flutter_wave_description',
                'payment_flutter_wave_public_key',
                'payment_flutter_wave_secret_key',
                'payment_flutter_wave_encryption_key',
                'payment_flutter_wave_status',
            ])->delete();
    }
}
