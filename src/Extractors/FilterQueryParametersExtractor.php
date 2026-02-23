<?php

namespace Admin9\ScrambleExtensions\Extractors;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;
use Mitoop\LaravelQueryBuilder\AbstractFilter;
use PhpParser\Node;
use PhpParser\NodeFinder;
use ReflectionClass;

class FilterQueryParametersExtractor implements ParameterExtractor
{
    private static array $resolvedClassNames = [];

    public static function resetCache(): void
    {
        self::$resolvedClassNames = [];
    }

    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        $filterClassName = $this->findFilterClassName($routeInfo);
        if (! $filterClassName || ! is_a($filterClassName, AbstractFilter::class, true)) {
            return $parameterExtractionResults;
        }

        $parameters = $this->extractFilterParameters($filterClassName);
        if (empty($parameters)) {
            return $parameterExtractionResults;
        }

        $parameterExtractionResults[] = new ParametersExtractionResult(
            parameters: $parameters,
        );

        return $parameterExtractionResults;
    }

    private function findFilterClassName(RouteInfo $routeInfo): ?string
    {
        $actionNode = $routeInfo->actionNode();
        if (! $actionNode) {
            return null;
        }

        $nodeFinder = new NodeFinder;

        $filterCall = $nodeFinder->findFirst($actionNode, function (Node $node) {
            if (! ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\StaticCall)) {
                return false;
            }

            return $node->name instanceof Node\Identifier
                && $node->name->name === 'filter'
                && isset($node->args[0])
                && $node->args[0]->value instanceof Node\Expr\ClassConstFetch
                && $node->args[0]->value->name instanceof Node\Identifier
                && $node->args[0]->value->name->name === 'class';
        });

        if (! $filterCall) {
            return null;
        }

        /** @var Node\Expr\ClassConstFetch $classConst */
        $classConst = $filterCall->args[0]->value;

        if (! $classConst->class instanceof Node\Name) {
            return null;
        }

        $shortName = $classConst->class->toString();

        return $this->resolveClassName($shortName, $routeInfo);
    }

    private function resolveClassName(string $shortName, RouteInfo $routeInfo): ?string
    {
        if (class_exists($shortName)) {
            return $shortName;
        }

        $controllerClass = $routeInfo->className();
        if (! $controllerClass) {
            return null;
        }

        $cacheKey = $controllerClass.'::'.$shortName;
        if (isset(self::$resolvedClassNames[$cacheKey])) {
            return self::$resolvedClassNames[$cacheKey];
        }

        $reflection = new ReflectionClass($controllerClass);
        $fileName = $reflection->getFileName();
        if (! $fileName) {
            return null;
        }

        $source = file_get_contents($fileName);
        if ($source === false) {
            return null;
        }

        $parser = (new \PhpParser\ParserFactory)->createForHostVersion();
        $ast = $parser->parse($source);
        if ($ast === null) {
            return null;
        }

        $nodeFinder = new NodeFinder;
        $useStmts = $nodeFinder->find($ast, fn (Node $node) => $node instanceof Node\Stmt\Use_);

        foreach ($useStmts as $useStmt) {
            foreach ($useStmt->uses as $use) {
                $alias = $use->alias ? $use->alias->name : $use->name->getLast();
                if ($alias === $shortName) {
                    return self::$resolvedClassNames[$cacheKey] = $use->name->toString();
                }
            }
        }

        $namespace = $reflection->getNamespaceName();
        $resolved = $namespace ? $namespace.'\\'.$shortName : $shortName;

        return self::$resolvedClassNames[$cacheKey] = $resolved;
    }

    /**
     * @return Parameter[]
     */
    private function extractFilterParameters(string $filterClassName): array
    {
        $parameters = [];

        $filterFields = $this->getFilterFields($filterClassName);
        foreach ($filterFields as $field) {
            $parameters[] = Parameter::make($field, 'query')
                ->setSchema(Schema::fromType(new StringType));
        }

        $allowedSorts = $this->getAllowedSorts($filterClassName);
        if (! empty($allowedSorts)) {
            $sortType = (new StringType)->enum($allowedSorts);
            $parameters[] = Parameter::make('sort', 'query')
                ->description('Sort field. Prefix with - for descending order.')
                ->setSchema(Schema::fromType($sortType));
        }

        $paginationConfig = config('scramble-extensions.filter.pagination', []);
        $pageSizeDefault = $paginationConfig['page_size_default'] ?? 15;
        $pageSizeMax = $paginationConfig['page_size_max'] ?? 100;

        $pageSizeType = (new IntegerType)->setMin(1)->setMax($pageSizeMax)->default($pageSizeDefault);
        $parameters[] = Parameter::make('page_size', 'query')
            ->description('Items per page.')
            ->setSchema(Schema::fromType($pageSizeType));

        $pageType = (new IntegerType)->setMin(1);
        $parameters[] = Parameter::make('page', 'query')
            ->description('Page number.')
            ->setSchema(Schema::fromType($pageType));

        return $parameters;
    }

    private function getFilterFields(string $filterClassName): array
    {
        try {
            $filter = new $filterClassName;
            $reflection = new ReflectionClass($filter);
            $method = $reflection->getMethod('rules');
            $rules = $method->invoke($filter);

            $fields = [];
            foreach ($rules as $key => $value) {
                $field = is_int($key) ? $value : $key;
                $field = explode('|', $field)[0];
                $fields[] = $field;
            }

            return $fields;
        } catch (\Throwable $e) {
            logger()->debug("ScrambleExtensions: Failed to extract filter fields from {$filterClassName}: {$e->getMessage()}");

            return [];
        }
    }

    private function getAllowedSorts(string $filterClassName): array
    {
        try {
            $reflection = new ReflectionClass($filterClassName);
            $property = $reflection->getProperty('allowedSorts');

            $default = $property->getDefaultValue();

            return is_array($default) ? $default : [];
        } catch (\Throwable $e) {
            logger()->debug("ScrambleExtensions: Failed to extract allowed sorts from {$filterClassName}: {$e->getMessage()}");

            return [];
        }
    }
}
