<?php

namespace Admin9\ScrambleExtensions\Tests;

use Admin9\ScrambleExtensions\Extensions\BusinessResponseInferExtension;
use Admin9\ScrambleExtensions\Extensions\ModelColumnDescriptionExtension;
use Admin9\ScrambleExtensions\Extractors\FilterQueryParametersExtractor;
use Admin9\ScrambleExtensions\ScrambleExtensionsServiceProvider;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mitoop\Http\RespondsWithJson;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ScrambleServiceProvider::class,
            ScrambleExtensionsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('scramble-extensions.response.trait', RespondsWithJson::class);
        $app['config']->set('scramble-extensions.response.model_namespace', 'Admin9\\ScrambleExtensions\\Tests\\Fixtures\\Models');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Scramble::$extensions = [];
        BusinessResponseInferExtension::resetCache();
        FilterQueryParametersExtractor::resetCache();
        ModelColumnDescriptionExtension::resetCache();
    }

    protected function migrateUsersTable(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('User identifier');
            $table->string('name')->comment('Display name');
            $table->timestamps();
        });
    }
}
