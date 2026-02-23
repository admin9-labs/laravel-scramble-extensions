<?php

namespace Admin9\ScrambleExtensions\Extractors;

use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RequestBodyExtension;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\GeneratesParametersFromRules;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest;
use ReflectionNamedType;

class SceneFormRequestParametersExtractor implements ParameterExtractor
{
    use GeneratesParametersFromRules;

    public function __construct(
        private TypeTransformer $openApiTransformer,
    ) {}

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        if (! $requestClassName = $this->getSceneFormRequestClassName($routeInfo)) {
            return $parameterExtractionResults;
        }

        $actionName = $routeInfo->methodName();
        if (! $actionName) {
            return $parameterExtractionResults;
        }

        $sceneMethod = $actionName.'Rules';
        if (! method_exists($requestClassName, $sceneMethod)) {
            return $parameterExtractionResults;
        }

        $rules = $this->resolveRules($requestClassName, $sceneMethod);
        if (empty($rules)) {
            return $parameterExtractionResults;
        }

        $in = in_array(mb_strtolower($routeInfo->method), RequestBodyExtension::HTTP_METHODS_WITHOUT_REQUEST_BODY)
            ? 'query'
            : 'body';

        $parameterExtractionResults[] = new ParametersExtractionResult(
            parameters: $this->makeParameters(
                rules: $this->normalizeRules($rules),
                typeTransformer: $this->openApiTransformer,
                rulesDocsRetriever: [],
                in: $in,
            ),
        );

        return $parameterExtractionResults;
    }

    private function getSceneFormRequestClassName(RouteInfo $routeInfo): ?string
    {
        $reflectionAction = $routeInfo->reflectionAction();
        if (! $reflectionAction) {
            return null;
        }

        foreach ($reflectionAction->getParameters() as $param) {
            $type = $param->getType();
            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $className = $type->getName();
            if (is_a($className, EfficientSceneFormRequest::class, true)) {
                return $className;
            }
        }

        return null;
    }

    private function resolveRules(string $requestClassName, string $sceneMethod): array
    {
        try {
            $instance = new $requestClassName;

            return app()->call([$instance, $sceneMethod]);
        } catch (\Throwable $e) {
            logger()->debug("ScrambleExtensions: Failed to resolve scene rules from {$requestClassName}::{$sceneMethod}: {$e->getMessage()}");

            return [];
        }
    }

    private function normalizeRules(array $rules): array
    {
        $normalized = [];
        foreach ($rules as $key => $rule) {
            if (is_string($rule)) {
                $rule = explode('|', $rule);
            }

            $normalized[$key] = is_array($rule) ? $rule : [$rule];
        }

        return $normalized;
    }
}
