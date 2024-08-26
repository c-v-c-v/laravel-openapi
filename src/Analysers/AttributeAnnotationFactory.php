<?php

namespace Cv\LaravelOpenApi\Analysers;

use Cv\LaravelOpenApi\Attributes\Operation;
use Cv\LaravelOpenApi\Attributes\RequestJsonContent;
use Exception;
use Illuminate\Routing\RouteCollectionInterface;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;
use OpenApi\Attributes\Delete;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Patch;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Put;
use OpenApi\Attributes\Response;
use OpenApi\Context;
use OpenApi\Generator;
use ReflectionAttribute;

class AttributeAnnotationFactory extends \OpenApi\Analysers\AttributeAnnotationFactory
{
    /**
     * @throws \ReflectionException
     * @throws Exception
     */
    public function build(\Reflector $reflector, Context $context): array
    {
        if (! $this->isSupported() || ! method_exists($reflector, 'getAttributes')) {
            return [];
        }

        if ($reflector instanceof \ReflectionProperty && method_exists($reflector, 'isPromoted') && $reflector->isPromoted()) {
            // handled via __construct() parameter
            return [];
        }

        // no proper way to inject
        Generator::$context = $context;

        /** @var OA\AbstractAnnotation[] $annotations */
        $annotations = [];
        try {
            foreach ($reflector->getAttributes() as $attribute) {
                if (class_exists($attribute->getName())) {
                    $instance = $attribute->newInstance();
                    if ($instance instanceof OA\AbstractAnnotation) {
                        $annotations[] = $instance;
                    } else {
                        if ($context->is('other') === false) {
                            $context->other = [];
                        }
                        $context->other[] = $instance;
                    }
                } else {
                    $context->logger->debug(sprintf('Could not instantiate attribute "%s"; class not found.', $attribute->getName()));
                }
            }

            if ($reflector instanceof \ReflectionMethod) {
                $hasOperation = (bool) collect($reflector->getAttributes())
                    ->first(function (ReflectionAttribute $reflectionAttribute) {
                        return $reflectionAttribute->getName() === Operation::class;
                    });
                if (! $hasOperation) {
                    $controller = $reflector->getDeclaringClass()->getName();
                    $method = $reflector->getName();
                    // 通过控制器+方法名 自动填充当前的路径
                    /** @var RouteCollectionInterface $routes */
                    $routes = app('router')->getRoutes();
                    $action = implode('@', [$controller, $method]);
                    $router = $routes->getByAction(ltrim($action, '\\'));
                    if (! empty($router)) {
                        // 填充路径
                        $annotation = match ($router->methods[0]) {
                            'GET' => new Get,
                            'POST' => new Post,
                            'PUT' => new Put,
                            'PATCH' => new Patch,
                            'DELETE' => new Delete,
                            default => throw new Exception("Unsupported method: $method"),
                        };

                        // 添加api用户验证
                        $securityProcessor = config('laravel-openapi.security-processor');
                        if ($securityProcessor) {
                            $security = (new $securityProcessor)($router);
                            if (! empty($security)) {
                                $annotation->security = $security;
                            }
                        }
                        $annotation->path = '/'.$router->uri();
                        $reflectionMethod = new \ReflectionMethod($controller, $method);

                        // 添加默认响应
                        $hasResponse = (bool) collect($reflectionMethod->getAttributes())
                            ->first(function (ReflectionAttribute $reflectionAttribute) {
                                return is_a($reflectionAttribute->getName(), Response::class, true);
                            });
                        if (! $hasResponse) {
                            $defaultResponseClass = config('laravel-openapi.default-response');
                            $annotations[] = new $defaultResponseClass;
                        }

                        $annotations[] = $annotation;
                    }
                }
            }

            if ($reflector instanceof \ReflectionMethod) {
                // also look at parameter attributes
                foreach ($reflector->getParameters() as $rp) {
                    foreach ([OA\Property::class, OA\Parameter::class, OA\RequestBody::class] as $attributeName) {
                        foreach ($rp->getAttributes($attributeName, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                            /** @var OA\Property|OA\Parameter|OA\RequestBody $instance */
                            $instance = $attribute->newInstance();

                            $type = (($rnt = $rp->getType()) && $rnt instanceof \ReflectionNamedType) ? $rnt->getName() : Generator::UNDEFINED;
                            $nullable = $rnt ? $rnt->allowsNull() : true;

                            if ($instance instanceof OA\RequestBody) {
                                $instance->required = ! $nullable;
                                if ($instance instanceof RequestJsonContent) {
                                    foreach ($instance->_unmerged as $annotation) {
                                        if ($annotation instanceof JsonContent) {
                                            if (empty($annotation->ref) || $annotation->ref === Generator::UNDEFINED) {
                                                $annotation->ref = $type;
                                            }
                                        }
                                    }
                                }
                            } elseif ($instance instanceof OA\Property) {
                                if (Generator::isDefault($instance->property)) {
                                    $instance->property = $rp->getName();
                                }
                                if (Generator::isDefault($instance->type)) {
                                    $instance->type = $type;
                                }
                                $instance->nullable = $nullable ?: Generator::UNDEFINED;

                                if ($rp->isPromoted()) {
                                    // ensure each property has its own context
                                    $instance->_context = new Context(['generated' => true, 'annotations' => [$instance]], $context);

                                    // promoted parameter - docblock is available via class/property
                                    if ($comment = $rp->getDeclaringClass()->getProperty($rp->getName())->getDocComment()) {
                                        $instance->_context->comment = $comment;
                                    }
                                }
                            } else {
                                if (! $instance->name || Generator::isDefault($instance->name)) {
                                    $instance->name = Str::snake($rp->getName());
                                }
                                $instance->required = ! $nullable;
                                $context = new Context(['nested' => $this], $context);
                                $context->comment = null;
                                $instance->merge([new OA\Schema(['type' => $type, '_context' => $context])]);
                            }
                            $annotations[] = $instance;
                        }
                    }
                }

                if (($rrt = $reflector->getReturnType()) && $rrt instanceof \ReflectionNamedType) {
                    foreach ($annotations as $annotation) {
                        if ($annotation instanceof OA\Property && Generator::isDefault($annotation->type)) {
                            // pick up simple return types
                            $annotation->type = $rrt->getName();
                        }
                    }
                }
            }
        } finally {
            Generator::$context = null;
        }

        $annotations = array_values(array_filter($annotations, function ($a) {
            return $a instanceof OA\AbstractAnnotation;
        }));

        // merge backwards into parents...
        $isParent = function (OA\AbstractAnnotation $annotation, OA\AbstractAnnotation $possibleParent): bool {
            // regular annotation hierarchy
            $explicitParent = $possibleParent->matchNested($annotation) !== null && ! $annotation instanceof OA\Attachable;

            $isParentAllowed = false;
            // support Attachable subclasses
            if ($isAttachable = $annotation instanceof OA\Attachable) {
                if (! $isParentAllowed = ($annotation->allowedParents() === null)) {
                    // check for allowed parents
                    foreach ($annotation->allowedParents() as $allowedParent) {
                        if ($possibleParent instanceof $allowedParent) {
                            $isParentAllowed = true;
                            break;
                        }
                    }
                }
            }

            // Property can be nested...
            return $annotation->getRoot() != $possibleParent->getRoot()
                && ($explicitParent || ($isAttachable && $isParentAllowed));
        };

        $annotationsWithoutParent = [];
        foreach ($annotations as $index => $annotation) {
            $mergedIntoParent = false;

            for ($ii = 0; $ii < count($annotations); $ii++) {
                if ($ii === $index) {
                    continue;
                }
                $possibleParent = $annotations[$ii];
                if ($isParent($annotation, $possibleParent)) {
                    $mergedIntoParent = true; //
                    $possibleParent->merge([$annotation]);
                }
            }

            if (! $mergedIntoParent) {
                $annotationsWithoutParent[] = $annotation;
            }
        }

        return $annotationsWithoutParent;
    }
}
