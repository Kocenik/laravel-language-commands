<?php

namespace Kocenik\translations;

use App\Console\Commands\AutoTranslations;
use App\Console\Commands\ExtractTranslations;
use App\Console\Commands\ResetTranslations;
use App\Console\Commands\WrapTranslations;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AutoTranslations::class,
                ExtractTranslations::class,
                ResetTranslations::class,
                WrapTranslations::class,
            ]);
        }
    }
}
