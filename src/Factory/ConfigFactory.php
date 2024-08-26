<?php

namespace Cv\LaravelOpenApi\Factory;

use OpenApi\Analysers\AnalyserInterface;

class ConfigFactory extends \L5Swagger\ConfigFactory
{
    public function documentationConfig(?string $documentation = null): array
    {
        $config = parent::documentationConfig($documentation);

        // 支持工厂模式创建 analyser
        $analyser = $config['scanOptions']['analyser'];
        if (! ($analyser instanceof AnalyserInterface)) {
            $config['scanOptions']['analyser'] = (new $analyser)();
        }

        return $config;
    }
}
