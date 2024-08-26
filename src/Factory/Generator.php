<?php

namespace Cv\LaravelOpenApi\Factory;

use Illuminate\Support\Arr;
use OpenApi\Generator as OpenApiGenerator;

class Generator extends \L5Swagger\Generator
{
    protected function setProcessors(OpenApiGenerator $generator): void
    {
        $processorClasses = Arr::get($this->scanOptions, self::SCAN_OPTION_PROCESSORS, []);

        $processorPipeline = $generator->getProcessorPipeline();
        $processors = $this->sortAndInsert($processorClasses, $processorPipeline->pipes());

        if (! empty($processors)) {
            $generator->setProcessors($processors);
        }
    }

    /**
     * @param  array<class-string>  $items
     * @param  object[]  $arr
     * @return object[]
     */
    protected function sortAndInsert(array $items, array $arr): array
    {
        /** @var array<class-string, object> $classToObj */
        $classToObj = [];
        foreach ($arr as $item) {
            $classToObj[$item::class] = $item;
        }

        // 排序存在的obj
        $has = [];
        /** @var object[] $sortObjs */
        $sortObjs = [];
        foreach ($items as $item) {
            $has[$item] = true;
            if (isset($classToObj[$item])) {
                $sortObjs[] = $classToObj[$item];
            }
        }
        foreach ($arr as $key => $item) {
            if (isset($has[$item::class]) && $has[$item::class] === true) {
                $arr[$key] = array_shift($sortObjs);
            }
        }

        /** @var object[] $newArr */
        $newArr = [];
        foreach ($arr as $obj) {
            if (isset($has[$obj::class]) && $has[$obj::class] === true) {
                // 在之前添加新的元素
                while (! empty($items) && current($items) !== $obj::class) {
                    $class = array_shift($items);
                    $newArr[] = new $class;
                }

                // 添加当前元素
                if (current($items) === $obj::class) {
                    array_shift($items);
                    $newArr[] = $obj;
                }

                // 添加它之后的元素且不在$arr中
                while (! empty($items) && ! isset($classToObj[current($items)])) {
                    $class = array_shift($items);
                    $newArr[] = new $class;
                }
            } else {
                $newArr[] = $obj;
            }
        }

        // 将剩余元素添加到末尾
        foreach ($items as $item) {
            $newArr[] = new $item;
        }

        return $newArr;
    }
}
