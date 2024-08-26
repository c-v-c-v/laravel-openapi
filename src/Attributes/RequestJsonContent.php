<?php

namespace Cv\LaravelOpenApi\Attributes;

use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\RequestBody;
use OpenApi\Generator;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
class RequestJsonContent extends RequestBody
{
    public function __construct(object|string|null $ref = null, mixed $example = Generator::UNDEFINED)
    {
        parent::__construct(content: new JsonContent(ref: $ref, example: $example));
    }
}
