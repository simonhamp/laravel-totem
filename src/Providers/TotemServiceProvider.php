<?php

namespace Studio\Totem\Providers;

use Studio\Totem\Totem;
use Cron\CronExpression;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use Studio\Totem\Contracts\TaskInterface;
use Studio\Totem\Notifications\TaskCompleted;
use Studio\Totem\Console\Commands\ListSchedule;
use Studio\Totem\Console\Commands\PublishAssets;
use Studio\Totem\Repositories\EloquentTaskRepository;
use Studio\Totem\Contracts\TaskCompleted as NotificationContract;

class TotemServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any services.
     *
     * @return void
     */
    public function boot()
    {
        try {
            if (Totem::baseTableExists()) {
                $this->app->register(ConsoleServiceProvider::class);
            }
        } catch (\PDOException $ex) {
            // This will trigger if DB cannot be connected to
            Log::error($ex->getMessage());
        }
        $this->registerResources();
        $this->defineAssetPublishing();

        Validator::extend('cron_expression', function ($attribute, $value, $parameters, $validator) {
            return CronExpression::isValidExpression($value);
        });

        Validator::extend('json_file', function ($attribute, UploadedFile $value, $validator) {
            return $value->getClientOriginalExtension() == 'json';
        });
    }

    /**
     * Register any services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/totem.php',
            'totem'
        );

        if (! defined('TOTEM_PATH')) {
            define('TOTEM_PATH', realpath(__DIR__.'/../../'));
        }

        if (! defined('TOTEM_TABLE_PREFIX')) {
            define('TOTEM_TABLE_PREFIX', config('totem.table_prefix'));
        }

        if (! defined('TOTEM_DATABASE_CONNECTION')) {
            define('TOTEM_DATABASE_CONNECTION', config('totem.database_connection', Schema::getConnection()->getName()));
        }

        $this->commands([
            ListSchedule::class,
            PublishAssets::class,
        ]);

        $this->app->bind(NotificationContract::class, TaskCompleted::class);
        $this->app->bindIf('totem.tasks', EloquentTaskRepository::class, true);
        $this->app->alias('totem.tasks', TaskInterface::class);
        $this->app->register(TotemRouteServiceProvider::class);
        $this->app->register(TotemEventServiceProvider::class);
        $this->app->register(TotemFormServiceProvider::class);
    }

    /**
     * Register the Totem resources.
     *
     * @return void
     */
    protected function registerResources()
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'totem');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'totem');
    }

    /**
     * Define the asset publishing configuration.
     *
     * @return void
     */
    public function defineAssetPublishing()
    {
        $this->publishes([
            TOTEM_PATH.'/public/js' => public_path('vendor/totem/js'),
        ], 'totem-assets');

        $this->publishes([
            TOTEM_PATH.'/public/css' => public_path('vendor/totem/css'),
        ], 'totem-assets');

        $this->publishes([
            TOTEM_PATH.'/public/img' => public_path('vendor/totem/img'),
        ], 'totem-assets');

        $this->publishes([
            TOTEM_PATH.'/config' => config_path(),
        ], 'totem-config');
    }
}
