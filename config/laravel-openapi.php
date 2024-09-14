<?php

use Cv\LaravelOpenApi\Attributes\DefaultResponse;
use Cv\LaravelOpenApi\SecurityProcessor;

return [
    'default-response' => DefaultResponse::class,
    'security-processor' => SecurityProcessor::class,
    'uri' => '/api/redoc',
    'route-name' => 'redoc',
    'spec-url-route-name' => ['l5-swagger.'.'default'.'.docs', 'api-docs.json'],
];
