<?php

namespace Admin9\ScrambleExtensions;

use Admin9\ScrambleExtensions\Extensions\BusinessResponseInferExtension;
use Admin9\ScrambleExtensions\Extensions\BusinessResponseOperationExtension;
use Admin9\ScrambleExtensions\Extractors\FilterQueryParametersExtractor;
use Admin9\ScrambleExtensions\Extractors\SceneFormRequestParametersExtractor;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\FormRequestParametersExtractor;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ScrambleExtensionsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('scramble-extensions')
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        $this->registerResponseExtensions();
        $this->registerParameterExtractors();

        if (config('scramble-extensions.response.enabled', true)) {
            $this->registerDocumentTransformers();
        }
    }

    private function registerResponseExtensions(): void
    {
        if (! config('scramble-extensions.response.enabled', true)) {
            return;
        }

        $trait = config('scramble-extensions.response.trait');
        if (! is_string($trait) || ! trait_exists($trait)) {
            return;
        }

        Scramble::configure()->operationTransformers->append([
            BusinessResponseOperationExtension::class,
        ]);

        Scramble::registerExtension(BusinessResponseInferExtension::class);
    }

    private function registerParameterExtractors(): void
    {
        $extractors = [];

        if (
            config('scramble-extensions.scene_form_request.enabled', true)
            && class_exists(\Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest::class)
        ) {
            $extractors[] = SceneFormRequestParametersExtractor::class;

            FormRequestParametersExtractor::ignoreInstanceOf(
                \Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest::class,
            );
        }

        if (
            config('scramble-extensions.filter.enabled', true)
            && class_exists(\Mitoop\LaravelQueryBuilder\AbstractFilter::class)
        ) {
            $extractors[] = FilterQueryParametersExtractor::class;
        }

        if (! empty($extractors)) {
            Scramble::configure()->parametersExtractors->prepend($extractors);
        }
    }

    private function registerDocumentTransformers(): void
    {
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $toRemove = [];
            foreach ($openApi->components->schemas as $name => $schema) {
                $type = $schema->type;
                if (
                    $type instanceof \Dedoc\Scramble\Support\Generator\Types\ObjectType
                    && $type->hasProperty('current_page')
                    && $type->hasProperty('data')
                    && $type->hasProperty('total')
                ) {
                    $toRemove[] = $name;
                }
            }
            foreach ($toRemove as $name) {
                $openApi->components->removeSchema($name);
            }
        });
    }
}
