<?php

namespace Cv\LaravelOpenApi;

use Illuminate\Support\ServiceProvider;
use L5Swagger\ConfigFactory;
use L5Swagger\GeneratorFactory;

class LaravelOpenApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerBind();
    }

    public function boot(): void
    {
        // 发布配置文件
        $this->publishes([
            __DIR__.'/../config/laravel-openapi.php' => config_path('laravel-openapi.php'),
        ], 'config');
    }

    private function registerBind(): void
    {
        $this->app->bind(ConfigFactory::class, Factory\ConfigFactory::class);
        $this->app->bind(GeneratorFactory::class, Factory\GeneratorFactory::class);
    }
}
