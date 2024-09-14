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

        if (! app()->configurationIsCached()) {
            $this->mergeConfigFrom(__DIR__.'/../config/laravel-openapi.php', 'laravel-openapi');
        }
    }

    public function boot(): void
    {
        // 发布配置文件
        $this->publishes([
            __DIR__.'/../config/laravel-openapi.php' => config_path('laravel-openapi.php'),
        ], 'config');

        // 发布视图
        $viewPath = __DIR__.'/../resources/views';
        $this->loadViewsFrom($viewPath, 'laravel-openapi');
        $this->publishes([
            $viewPath => resource_path('views/vendor/cv/laravel-openapi'),
        ], 'views');

        // 加载路由
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    private function registerBind(): void
    {
        $this->app->bind(ConfigFactory::class, Factory\ConfigFactory::class);
        $this->app->bind(GeneratorFactory::class, Factory\GeneratorFactory::class);
    }
}
