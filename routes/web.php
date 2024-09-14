<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get(config('laravel-openapi.uri'), function () {
    $view = view('laravel-openapi::redoc', [
        'url' => route(...config('laravel-openapi.spec-url-route-name')),
    ]);

    // 开发和测试环境跳过验证权限
    if (app()->isLocal() || app()->runningUnitTests()) {
        return $view;
    }

    Gate::authorize('openapi.view');

    return $view;
})->name(config('laravel-openapi.route-name'));
