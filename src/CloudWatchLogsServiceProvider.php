<?php

namespace HaseebHashim\CloudWatchLogs;

use Illuminate\Support\ServiceProvider;

class CloudWatchLogsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cloudwatch-logs.php', 'cloudwatch-logs'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration file
        $this->publishes([
            __DIR__.'/../config/cloudwatch-logs.php' => config_path('cloudwatch-logs.php'),
        ], 'config');
    }
}
