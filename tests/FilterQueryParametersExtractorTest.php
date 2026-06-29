<?php

namespace Admin9\ScrambleExtensions\Tests;

use Admin9\ScrambleExtensions\Extractors\FilterQueryParametersExtractor;
use Admin9\ScrambleExtensions\Tests\Fixtures\Controllers\UserController;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;

class FilterQueryParametersExtractorTest extends TestCase
{
    public function test_extracts_filter_sort_and_pagination_query_parameters(): void
    {
        $results = (new FilterQueryParametersExtractor)->handle($this->routeInfo(), []);

        $parameters = $results[0]->parameters;
        $parametersByName = collect($parameters)->keyBy('name');

        $this->assertSame(['name', 'email', 'status', 'sort', 'page_size', 'page'], $parametersByName->keys()->all());
        $this->assertSame('query', $parametersByName['name']->in);
        $this->assertSame(['created_at', 'name'], $parametersByName['sort']->schema->toArray()['enum']);
        $this->assertSame(15, $parametersByName['page_size']->schema->toArray()['default']);
        $this->assertSame(100, $parametersByName['page_size']->schema->toArray()['maximum']);
    }

    private function routeInfo(): RouteInfo
    {
        return new RouteInfo(
            new Route('GET', '/users', ['uses' => UserController::class.'@index']),
            'get',
        );
    }
}
