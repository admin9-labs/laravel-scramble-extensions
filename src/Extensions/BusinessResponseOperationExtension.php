<?php

namespace Admin9\ScrambleExtensions\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType as OpenApiArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType as OpenApiBooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType as OpenApiIntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType as OpenApiStringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\ObjectType;

/**
 * Wraps all 200 responses in the business envelope:
 * {success, code, message, data, [meta], request_id}
 */
class BusinessResponseOperationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! $operation->responses) {
            return;
        }

        foreach ($operation->responses as $i => $response) {
            if ($response instanceof Reference) {
                continue;
            }

            if (! $response instanceof Response || $response->code !== 200) {
                continue;
            }

            $jsonSchema = $response->content['application/json'] ?? null;
            if (! $jsonSchema instanceof Schema) {
                continue;
            }

            $originalType = $jsonSchema->type;

            if ($originalType instanceof OpenApiObjectType && $originalType->hasProperty('success')) {
                continue;
            }

            $resolvedType = $this->resolveType($originalType);

            if ($this->isPaginatedResponse($response, $operation, $resolvedType)) {
                $response->setContent('application/json', Schema::fromType(
                    $this->wrapPaginated($resolvedType, $routeInfo)
                ));
                $response->description = 'Paginated list';
            } else {
                $response->setContent('application/json', Schema::fromType(
                    $this->wrapStandard($originalType)
                ));
            }

            $operation->responses[$i] = $response;
        }
    }

    private function resolveType(OpenApiType $type): OpenApiType
    {
        if ($type instanceof Reference) {
            try {
                $resolved = $type->resolve();

                return $resolved instanceof Schema ? $resolved->type : $type;
            } catch (\Throwable) {
                return $type;
            }
        }

        return $type;
    }

    private function isPaginatorSchema(OpenApiType $type): bool
    {
        return $type instanceof OpenApiObjectType
            && $type->hasProperty('current_page')
            && $type->hasProperty('data')
            && $type->hasProperty('total');
    }

    private function isPaginatedResponse(Response $response, Operation $operation, OpenApiType $type): bool
    {
        if ($this->isPaginatorSchema($type)) {
            return true;
        }

        if (! $type instanceof OpenApiArrayType) {
            return false;
        }

        if (str_starts_with($response->description, 'Paginated set')) {
            return true;
        }

        return $this->hasPaginationParameters($operation);
    }

    private function hasPaginationParameters(Operation $operation): bool
    {
        $queryParameters = collect($operation->parameters)
            ->filter(fn ($parameter): bool => $parameter instanceof Parameter && $parameter->in === 'query')
            ->pluck('name')
            ->all();

        return in_array('page', $queryParameters, true)
            && in_array('page_size', $queryParameters, true);
    }

    private function wrapStandard(OpenApiType $dataType): OpenApiObjectType
    {
        $dataType = $this->normalizeBusinessDataType($dataType);

        $envelope = new OpenApiObjectType;
        $envelope->addProperty('success', (new OpenApiBooleanType)->setDescription('Whether the request was successful'));
        $envelope->addProperty('code', (new OpenApiIntegerType)->setDescription('Business status code, 0 = success'));
        $envelope->addProperty('message', new OpenApiStringType);
        $envelope->addProperty('data', $dataType);
        $envelope->addProperty('request_id', (new OpenApiStringType)->setDescription('UUID7 for request tracing'));
        $envelope->setRequired(['success', 'code', 'message', 'data', 'request_id']);

        return $envelope;
    }

    private function normalizeBusinessDataType(OpenApiType $dataType): OpenApiType
    {
        if (! $dataType instanceof OpenApiObjectType) {
            return $dataType;
        }

        $schemaOverrides = config('scramble-extensions.response.schema_overrides', []);
        if (! is_array($schemaOverrides) || $schemaOverrides === []) {
            return $dataType;
        }

        $normalizedType = null;

        foreach ($schemaOverrides as $property => $schemaType) {
            if (! is_string($property) || ! $dataType->hasProperty($property)) {
                continue;
            }

            $propertyType = $this->schemaOverrideType($schemaType);
            if (! $propertyType) {
                continue;
            }

            $normalizedType ??= $dataType->clone();
            $normalizedType->addProperty($property, $propertyType);
        }

        return $normalizedType ?? $dataType;
    }

    private function schemaOverrideType(mixed $schemaType): ?OpenApiType
    {
        if ($schemaType !== 'string_list') {
            return null;
        }

        return (new OpenApiArrayType)->setItems(new OpenApiStringType);
    }

    private function wrapPaginated(OpenApiType $paginatorType, RouteInfo $routeInfo): OpenApiObjectType
    {
        $itemsType = $paginatorType instanceof OpenApiObjectType && $paginatorType->hasProperty('data')
            ? $paginatorType->getProperty('data')
            : $paginatorType;

        if (! $itemsType instanceof OpenApiArrayType) {
            $modelSchema = $this->inferModelSchema($routeInfo);
            $arr = new OpenApiArrayType;
            if ($modelSchema) {
                $arr->setItems($modelSchema);
            }
            $itemsType = $arr;
        }

        $meta = new OpenApiObjectType;
        $meta->addProperty('pagination', (new OpenApiStringType)->setDescription('Pagination strategy'));
        $meta->addProperty('page', (new OpenApiIntegerType)->setDescription('Current page number'));
        $meta->addProperty('page_size', (new OpenApiIntegerType)->setDescription('Items per page'));
        $meta->addProperty('has_more', (new OpenApiBooleanType)->setDescription('Whether more pages exist'));
        $meta->addProperty('total', (new OpenApiIntegerType)->setDescription('Total number of items'));
        $meta->setRequired(['pagination', 'page', 'page_size', 'has_more', 'total']);

        $envelope = new OpenApiObjectType;
        $envelope->addProperty('success', (new OpenApiBooleanType)->setDescription('Whether the request was successful'));
        $envelope->addProperty('code', (new OpenApiIntegerType)->setDescription('Business status code, 0 = success'));
        $envelope->addProperty('message', new OpenApiStringType);
        $envelope->addProperty('data', $itemsType);
        $envelope->addProperty('meta', $meta);
        $envelope->addProperty('request_id', (new OpenApiStringType)->setDescription('UUID7 for request tracing'));
        $envelope->setRequired(['success', 'code', 'message', 'data', 'meta', 'request_id']);

        return $envelope;
    }

    private function inferModelSchema(RouteInfo $routeInfo): ?OpenApiType
    {
        $className = $routeInfo->className();
        if (! $className) {
            return null;
        }

        $shortClass = class_basename($className);
        $modelName = str_replace('Controller', '', $shortClass);

        if (! $modelName) {
            return null;
        }

        $modelNamespace = config('scramble-extensions.response.model_namespace', 'App\\Models');
        $modelClass = $modelNamespace.'\\'.$modelName;

        if (! class_exists($modelClass)) {
            return null;
        }

        $phpType = new ObjectType($modelClass);

        return $this->openApiTransformer->transform($phpType);
    }
}
