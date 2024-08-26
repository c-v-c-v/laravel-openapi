<?php

namespace Cv\LaravelOpenApi\Factory;

use Cv\LaravelOpenApi\Analysers\AttributeAnnotationFactory;
use OpenApi\Analysers\ReflectionAnalyser;

class AnalyserFactory
{
    public function __invoke(): ReflectionAnalyser
    {
        return new ReflectionAnalyser([new AttributeAnnotationFactory]);
    }
}
