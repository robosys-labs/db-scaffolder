<?php

namespace Robosys\DBScaffolder;

use Illuminate\Support\ServiceProvider;
use Robosys\DBScaffolder\Commands\BuildDBCommand;

class DBScaffolderServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__.'/../config/db_scaffolder.php';

        $this->publishes([
            $configPath => config_path('db_scaffolder.php'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('magic', function ($app) {
            return new BuildDBCommand();
        });


        $this->commands([
            'magic',
        ]);
    }
}
