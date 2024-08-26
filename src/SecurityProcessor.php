<?php

namespace Cv\LaravelOpenApi;

use Illuminate\Routing\Route;

class SecurityProcessor
{
    public function __invoke(Route $route): array
    {
        // return [
        //     [
        //         'sanctum' => [],
        //     ],
        // ];

        return [];
    }
}
