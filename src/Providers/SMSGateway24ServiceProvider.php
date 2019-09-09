<?php

namespace BotMan\Drivers\SMSGateway24\Providers;

use BotMan\Drivers\SMSGateway24\SMSGateway24Driver;
use Illuminate\Support\ServiceProvider;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Studio\Providers\StudioServiceProvider;

class SMSGateway24ServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if (! $this->isRunningInBotManStudio()) {
            $this->loadDrivers();

            $this->publishes([
                __DIR__.'/../../stubs/smsgateway24.php' => config_path('botman/smsgateway24.php'),
            ]);

            $this->mergeConfigFrom(__DIR__.'/../../stubs/smsgateway24.php', 'botman.smsgateway24');
        }
    }

    /**
     * Load BotMan drivers.
     */
    protected function loadDrivers()
    {
        DriverManager::loadDriver(SMSGateway24Driver::class);
    }

    /**
     * @return bool
     */
    protected function isRunningInBotManStudio()
    {
        return class_exists(StudioServiceProvider::class);
    }
}
