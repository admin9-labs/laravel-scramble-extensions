<?php

namespace Admin9\ScrambleExtensions\Tests;

use Admin9\ScrambleExtensions\Extractors\SceneFormRequestParametersExtractor;
use Admin9\ScrambleExtensions\Tests\Fixtures\Controllers\UserController;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;

class SceneFormRequestParametersExtractorTest extends TestCase
{
    public function test_extracts_rules_for_current_controller_action_scene(): void
    {
        $extractor = $this->extractor();

        $storeResults = $extractor->handle($this->routeInfo('POST', 'store'), []);
        $updateResults = $extractor->handle($this->routeInfo('PATCH', 'update'), []);

        $storeParameters = collect($storeResults[0]->parameters)->keyBy('name');
        $updateParameters = collect($updateResults[0]->parameters)->keyBy('name');

        $this->assertSame(['name', 'email'], $storeParameters->keys()->all());
        $this->assertTrue($storeParameters['name']->required);
        $this->assertTrue($storeParameters['email']->required);
        $this->assertSame(['name'], $updateParameters->keys()->all());
        $this->assertFalse($updateParameters['name']->required);
    }

    private function extractor(): SceneFormRequestParametersExtractor
    {
        $openApi = OpenApi::make('3.1.0')->setComponents(new Components);
        $config = new GeneratorConfig;
        $context = new OpenApiContext($openApi, $config);

        return new SceneFormRequestParametersExtractor(
            app()->make(TypeTransformer::class, ['context' => $context]),
        );
    }

    private function routeInfo(string $httpMethod, string $controllerMethod): RouteInfo
    {
        return new RouteInfo(
            new Route($httpMethod, '/users', ['uses' => UserController::class.'@'.$controllerMethod]),
            strtolower($httpMethod),
        );
    }
}
