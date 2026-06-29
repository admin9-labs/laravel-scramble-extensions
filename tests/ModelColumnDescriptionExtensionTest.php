<?php

namespace Admin9\ScrambleExtensions\Tests;

use Admin9\ScrambleExtensions\Extensions\ModelColumnDescriptionExtension;
use Admin9\ScrambleExtensions\Tests\Fixtures\Models\CommentedModel;
use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Support\Type\ObjectType;

class ModelColumnDescriptionExtensionTest extends TestCase
{
    public function test_reads_database_column_comments_into_model_property_doc_node(): void
    {
        $type = (new ModelColumnDescriptionExtension)->getPropertyType(
            new PropertyFetchEvent(new ObjectType(CommentedModel::class), 'name', new GlobalScope),
        );

        $this->assertNotNull($type);
        $this->assertStringContainsString('Display name', (string) $type->getAttribute('docNode'));
    }
}
