<?php

namespace Admin9\ScrambleExtensions\Tests\Schema;

use Admin9\ScrambleExtensions\Schema\Types\ClosedObjectType;
use Admin9\ScrambleExtensions\Tests\TestCase;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;

class ClosedObjectTypeTest extends TestCase
{
    public static function serializers(): array
    {
        return [['toArray'], ['serializeAs31'], ['serializeAs32']];
    }

    #[DataProvider('serializers')]
    public function test_empty_object_uses_the_standard_keyword(string $serializer): void
    {
        $schema = (new ClosedObjectType)->{$serializer}();

        $this->assertSame(['type' => 'object', 'additionalProperties' => false], $schema);
    }

    public function test_closed_nested_objects_and_references_survive_full_document_serialization(): void
    {
        $type = (new ClosedObjectType)
            ->addProperty('nested', (new ClosedObjectType)->addProperty('name', new StringType))
            ->setRequired(['nested']);
        $document = OpenApi::make('3.1.0')->setInfo(new InfoObject('Business response'));
        $reference = $document->components->addSchema('Payload', Schema::fromType($type));
        $document->components->addSchema('Container', Schema::fromType((new ObjectType)->addProperty('payload', $reference)));

        $documents = [$document->toArray()];
        if (method_exists($document, 'serializeAs32')) {
            $documents[] = $document->serializeAs32();
        }

        foreach ($documents as $serialized) {
            $schemas = $serialized['components']['schemas'];
            $this->assertSame(false, $schemas['Payload']['additionalProperties']);
            $this->assertSame(false, $schemas['Payload']['properties']['nested']['additionalProperties']);
            $this->assertSame(['nested'], $schemas['Payload']['required']);
            $this->assertSame('string', $schemas['Payload']['properties']['nested']['properties']['name']['type']);
            $this->assertSame('#/components/schemas/Payload', $schemas['Container']['properties']['payload']['$ref']);
            $this->assertArrayNotHasKey('additionalProperties', $schemas['Container']);
        }
    }

    public function test_cloning_preserves_closed_objects_and_isolates_nested_changes(): void
    {
        $original = (new ClosedObjectType)->addProperty('nested', (new ClosedObjectType)->addProperty('name', (new StringType)->setMax(10)));
        $clone = $original->clone();

        $clone->properties['nested']->properties['name']->setMax(20);

        $this->assertSame(10, $original->toArray()['properties']['nested']['properties']['name']['maxLength']);
        $this->assertSame(20, $clone->toArray()['properties']['nested']['properties']['name']['maxLength']);
        $this->assertSame(false, $clone->toArray()['additionalProperties']);
        $this->assertSame(false, $clone->toArray()['properties']['nested']['additionalProperties']);
    }

    #[DataProvider('serializers')]
    public function test_rejects_a_conflicting_typed_additional_properties_schema(string $serializer): void
    {
        $type = (new ClosedObjectType)->additionalProperties(new StringType);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Closed objects cannot have a typed additional-properties schema.');

        $type->{$serializer}();
    }

    public function test_ordinary_objects_keep_typed_additional_properties(): void
    {
        $type = (new ObjectType)->additionalProperties((new StringType)->setMax(10));

        $this->assertSame(['type' => 'string', 'maxLength' => 10], $type->toArray()['additionalProperties']);
    }
}
