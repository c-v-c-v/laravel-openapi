<?php

namespace Cv\LaravelOpenApi\Attributes;

use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;

class DefaultResponse extends Response
{
    public function __construct()
    {
        parent::__construct(response: 200, description: 'OK', content: new JsonContent(properties: [
            new Property('message', description: '消息', type: 'string'),
            new Property('data'),
        ]));
    }
}
