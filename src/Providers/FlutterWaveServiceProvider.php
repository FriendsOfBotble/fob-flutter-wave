<?php

namespace FriendsOfBotble\FlutterWave\Providers;

use Botble\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Support\ServiceProvider;

class FlutterWaveServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public const MODULE_NAME = 'flutter_wave';

    public function boot(): void
    {
        if (! is_plugin_active('payment')) {
            return;
        }

        $this->setNamespace('plugins/flutter-wave')
            ->loadAndPublishTranslations()
            ->loadAndPublishViews()
            ->publishAssets()
            ->loadRoutes();

        $this->app->booted(function () {
            $this->app->register(HookServiceProvider::class);
        });
    }
}
