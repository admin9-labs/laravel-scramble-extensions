<?php

namespace Admin9\ScrambleExtensions\Tests;

use Admin9\ScrambleExtensions\Extensions\BusinessResponseOperationExtension;
use Admin9\ScrambleExtensions\Tests\Fixtures\Controllers\UserController;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;

class BusinessResponseOperationExtensionTest extends TestCase
{
    public function test_wraps_standard_200_json_response_in_business_envelope(): void
    {
        $operation = Operation::make('get')->addResponse(
            Response::make(200)->setContent('application/json', Schema::fromType(new StringType))
        );

        $this->extension()->handle($operation, $this->routeInfo('show'));

        $schema = $operation->responses[0]->getContent('application/json')->toArray();

        $this->assertSame(['success', 'code', 'message', 'data', 'request_id'], $schema['required']);
        $this->assertSame('boolean', $schema['properties']['success']['type']);
        $this->assertSame('integer', $schema['properties']['code']['type']);
        $this->assertSame('string', $schema['properties']['message']['type']);
        $this->assertSame('string', $schema['properties']['data']['type']);
        $this->assertSame('string', $schema['properties']['request_id']['type']);
    }

    public function test_wraps_paginator_schema_in_business_pagination_envelope(): void
    {
        $paginatorType = (new ObjectType)
            ->addProperty('current_page', new IntegerType)
            ->addProperty('data', (new ArrayType)->setItems(new StringType))
            ->addProperty('total', new IntegerType);

        $operation = Operation::make('get')->addResponse(
            Response::make(200)->setContent('application/json', Schema::fromType($paginatorType))
        );

        $this->extension()->handle($operation, $this->routeInfo('index'));

        $schema = $operation->responses[0]->getContent('application/json')->toArray();

        $this->assertSame('Paginated list', $operation->responses[0]->description);
        $this->assertSame(['success', 'code', 'message', 'data', 'meta', 'request_id'], $schema['required']);
        $this->assertSame('array', $schema['properties']['data']['type']);
        $this->assertSame(
            ['pagination', 'page', 'page_size', 'has_more', 'total'],
            $schema['properties']['meta']['required'],
        );
        $this->assertArrayNotHasKey('current_page', $schema['properties']);
    }

    public function test_wraps_paginated_resource_collection_array_in_business_pagination_envelope(): void
    {
        $response = Response::make(200)
            ->description('Paginated set of `UserResource`')
            ->setContent('application/json', Schema::fromType((new ArrayType)->setItems(new StringType)));
        $operation = Operation::make('get')->addResponse($response);

        $this->extension()->handle($operation, $this->routeInfo('index'));

        $schema = $operation->responses[0]->getContent('application/json')->toArray();

        $this->assertSame('Paginated list', $operation->responses[0]->description);
        $this->assertSame(['success', 'code', 'message', 'data', 'meta', 'request_id'], $schema['required']);
        $this->assertSame('array', $schema['properties']['data']['type']);
        $this->assertSame('string', $schema['properties']['data']['items']['type']);
        $this->assertSame(
            ['pagination', 'page', 'page_size', 'has_more', 'total'],
            $schema['properties']['meta']['required'],
        );
    }

    public function test_wraps_filter_paginated_array_response_in_business_pagination_envelope(): void
    {
        $operation = Operation::make('get')
            ->addResponse(Response::make(200)->setContent(
                'application/json',
                Schema::fromType((new ArrayType)->setItems(new StringType)),
            ))
            ->addParameters([
                Parameter::make('page_size', 'query'),
                Parameter::make('page', 'query'),
            ]);

        $this->extension()->handle($operation, $this->routeInfo('index'));

        $schema = $operation->responses[0]->getContent('application/json')->toArray();

        $this->assertSame('Paginated list', $operation->responses[0]->description);
        $this->assertSame(['success', 'code', 'message', 'data', 'meta', 'request_id'], $schema['required']);
    }

    public function test_keeps_bounded_array_response_as_standard_business_envelope(): void
    {
        $response = Response::make(200)
            ->description('Array of `UserResource`')
            ->setContent('application/json', Schema::fromType((new ArrayType)->setItems(new StringType)));
        $operation = Operation::make('get')->addResponse($response);

        $this->extension()->handle($operation, $this->routeInfo('index'));

        $schema = $operation->responses[0]->getContent('application/json')->toArray();

        $this->assertSame(['success', 'code', 'message', 'data', 'request_id'], $schema['required']);
        $this->assertArrayNotHasKey('meta', $schema['properties']);
    }

    private function extension(): BusinessResponseOperationExtension
    {
        $openApi = OpenApi::make('3.1.0')->setComponents(new Components);
        $config = new GeneratorConfig;
        $context = new OpenApiContext($openApi, $config);

        return new BusinessResponseOperationExtension(
            app(Infer::class),
            app()->make(TypeTransformer::class, ['context' => $context]),
            $config,
        );
    }

    private function routeInfo(string $method): RouteInfo
    {
        return new RouteInfo(
            new Route('GET', '/users', ['uses' => UserController::class.'@'.$method]),
            'get',
        );
    }
}
