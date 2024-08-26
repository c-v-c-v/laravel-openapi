<?php

namespace Cv\LaravelOpenApi\Processors;

use DateTimeInterface;
use Exception;
use Illuminate\Support\Str;
use OpenApi\Analysis;
use OpenApi\Annotations\Operation;
use OpenApi\Attributes\Tag;
use OpenApi\Generator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

class LaravelRouteProcessor
{
    /**
     * @throws \ReflectionException
     * @throws Exception
     */
    public function __invoke(Analysis $analysis): void
    {
        foreach ($analysis->annotations as $annotation) {
            if ($annotation instanceof Operation) {
                $controller = $annotation->_context->fullyQualifiedName($annotation->_context->class);

                // 将控制器上的定义的tag合并到方法路由中去
                /** @var ?ReflectionAttribute $reflectionAttribute */
                $reflectionAttribute = (new ReflectionClass($controller))->getAttributes(Tag::class)[0] ?? null;
                if ($reflectionAttribute) {
                    /** @var Tag $tag */
                    $tag = $reflectionAttribute->newInstance();
                    /** @phpstan-ignore-next-line  */
                    if ($annotation->tags === Generator::UNDEFINED) {
                        $annotation->tags = [];
                    }

                    if (! in_array($tag->name, $annotation->tags)) {
                        $annotation->tags = array_merge($annotation->tags, [$tag->name]);
                    }
                }
            }
        }

        if (is_array($analysis->openapi->components->schemas)) {
            foreach ($analysis->openapi->components->schemas as $schema) {
                if (! is_array($schema->properties)) {
                    continue;
                }
                foreach ($schema->properties as $property) {
                    $fullyQualifiedClassName = $property->_context->fullyQualifiedName($property->_context->class);
                    if ($property->_context->class && class_exists($fullyQualifiedClassName)) {
                        $reflectionProperty = new ReflectionProperty(
                            $fullyQualifiedClassName,
                            $property->property
                        );
                        $reflectionIntersectionType = $reflectionProperty->getType();
                        if ($reflectionIntersectionType instanceof ReflectionNamedType) {
                            $isDateTime = is_a($reflectionIntersectionType->getName(), DateTimeInterface::class, true);
                            if ($isDateTime) {
                                $property->type = 'string';
                                $property->format = 'date-time';
                            }
                        }

                        if ($reflectionIntersectionType instanceof ReflectionUnionType) {
                            foreach ($reflectionIntersectionType->getTypes() as $type) {
                                $isDateTime = is_a($type->getName(), DateTimeInterface::class, true);
                                if ($isDateTime) {
                                    $property->type = 'string';
                                    $property->format = 'date-time';
                                    break;
                                }
                            }
                        }
                    }

                    $property->property = Str::snake($property->property);
                }
            }
        }

    }
}
