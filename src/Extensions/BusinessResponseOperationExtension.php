<?php

namespace Admin9\ScrambleExtensions\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
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

            if ($this->isPaginatorSchema($resolvedType)) {
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
            $resolved = $type->resolve();

            return $resolved instanceof Schema ? $resolved->type : $type;
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

    private function wrapStandard(OpenApiType $dataType): OpenApiObjectType
    {
        $envelope = new OpenApiObjectType;
        $envelope->addProperty('success', (new OpenApiBooleanType)->setDescription('Whether the request was successful'));
        $envelope->addProperty('code', (new OpenApiIntegerType)->setDescription('Business status code, 0 = success'));
        $envelope->addProperty('message', new OpenApiStringType);
        $envelope->addProperty('data', $dataType);
        $envelope->addProperty('request_id', (new OpenApiStringType)->setDescription('UUID7 for request tracing'));
        $envelope->setRequired(['success', 'code', 'message', 'data', 'request_id']);

        return $envelope;
    }

    private function wrapPaginated(OpenApiObjectType $paginatorType, RouteInfo $routeInfo): OpenApiObjectType
    {
        $itemsType = $paginatorType->getProperty('data') ?? new OpenApiArrayType;

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

        $phpType = new \Dedoc\Scramble\Support\Type\ObjectType($modelClass);

        return $this->openApiTransformer->transform($phpType);
    }
}
