<?php

namespace Cv\LaravelOpenApi\Attributes;

use OpenApi\Attributes\Get;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Operation extends Get {}
