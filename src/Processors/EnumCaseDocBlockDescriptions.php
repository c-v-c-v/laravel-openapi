<?php

namespace Cv\LaravelOpenApi\Processors;

use OpenApi\Analysis;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use OpenApi\Annotations\ServerVariable;
use OpenApi\Generator;
use OpenApi\Processors\Concerns\DocblockTrait;
use ReflectionEnum;
use UnitEnum;

class EnumCaseDocBlockDescriptions
{
    use DocblockTrait;

    public function __invoke(Analysis $analysis): void
    {
        // 如果通过schema引用的枚举,上级添加枚举注释
        $properties = $analysis->getAnnotationsOfType([Schema::class, ServerVariable::class]);
        foreach ($properties as $schema) {
            /** @var $schema Schema|ServerVariable */
            /** @phpstan-ignore-next-line  */
            if (Generator::isDefault($schema->enum)) {
                continue;
            }

            /** @phpstan-ignore-next-line  */
            if (is_a($schema->enum, UnitEnum::class, true)) {
                $this->addEnumCaseComment($schema->_context->nested, new ReflectionEnum($schema->enum));
            }
        }

        // 如果属性引用了枚举，则添加枚举注释
        $properties = $analysis->getAnnotationsOfType(Property::class);
        foreach ($properties as $propertyAnnotation) {
            if ($propertyAnnotation instanceof Property) {
                $fullyQualifiedClassName = $propertyAnnotation->_context->fullyQualifiedName($propertyAnnotation->_context->class);
                $property = $propertyAnnotation->_context->property;
                if (! class_exists($fullyQualifiedClassName) || empty($property)) {
                    // 手动指定
                    if (
                        $propertyAnnotation->type === 'enum'
                        && is_a($propertyAnnotation->enum, UnitEnum::class, true)
                    ) {
                        $this->addEnumCaseComment($propertyAnnotation, new ReflectionEnum($propertyAnnotation->enum));
                    }
                } else {
                    // schema的property应用
                    $reflectionProperty = new \ReflectionProperty($fullyQualifiedClassName, $propertyAnnotation->_context->property);
                    $type = $reflectionProperty->getType();
                    if ($type instanceof \ReflectionNamedType) {
                        if (is_a($type->getName(), UnitEnum::class, true)) {
                            $this->addEnumCaseComment($propertyAnnotation, new ReflectionEnum($type->getName()));
                        }
                    }
                }
            }
        }

        // 枚举schema
        if (is_array($analysis->openapi->components->schemas)) {
            foreach ($analysis->openapi->components->schemas as $schema) {
                if ($schema->_context->is('enum') && $schema instanceof Schema) {
                    $fullyQualifiedClassName = $schema->_context->fullyQualifiedName($schema->_context->enum);
                    $reflectionEnum = new ReflectionEnum($fullyQualifiedClassName);

                    $this->addEnumCaseComment($schema, $reflectionEnum);
                }
            }
        }
    }

    protected function addEnumCaseComment(mixed $schema, ReflectionEnum $reflectionEnum): void
    {
        $enumDesc = '';
        foreach ($reflectionEnum->getCases() as $case) {
            $desc = $this->extractContent($case->getDocComment());
            if ($desc === Generator::UNDEFINED) {
                continue;
            }

            if ($case instanceof \ReflectionEnumBackedCase) {
                $enumDesc .= $case->getBackingValue().': '.$desc."  \n  ";
            } else {
                $enumDesc .= $case->getValue()->name.': '.$desc."  \n  ";
            }
        }
        $enumDesc = rtrim($enumDesc);

        if (! empty($enumDesc)) {
            if ($schema->description === Generator::UNDEFINED) {
                $schema->description = $enumDesc;
            } else {
                $schema->description .= "  \n  ".$enumDesc;
            }
        }
    }
}
