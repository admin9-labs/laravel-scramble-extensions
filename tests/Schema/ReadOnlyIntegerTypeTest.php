<?php

namespace Admin9\ScrambleExtensions\Tests\Schema;

use Admin9\ScrambleExtensions\Schema\Concerns\ReadOnlySerialization;
use Admin9\ScrambleExtensions\Schema\Types\ReadOnlyIntegerType;
use Admin9\ScrambleExtensions\Tests\TestCase;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

class ReadOnlyIntegerTypeTest extends TestCase
{
    public function test_direct_serialization_preserves_integer_constraints(): void
    {
        $type = (new ReadOnlyIntegerType)->setMin(1)->setMax(100)->format('int64')->enum([1, 2])->setDescription('Identifier');

        $schema = $type->toArray();

        $this->assertSame('integer', $schema['type']);
        $this->assertSame(true, $schema['readOnly']);
        $this->assertSame(1, $schema['minimum']);
        $this->assertSame(100, $schema['maximum']);
        $this->assertSame('int64', $schema['format']);
        $this->assertSame([1, 2], $schema['enum']);
        $this->assertSame('Identifier', $schema['description']);
        $this->assertArrayNotHasKey('x-readOnly', $schema);
    }

    public function test_read_only_fields_survive_full_document_serialization(): void
    {
        $string = new class extends StringType
        {
            use ReadOnlySerialization;
        };
        $type = (new ObjectType)
            ->addProperty('nested', (new ObjectType)->addProperty('id', new ReadOnlyIntegerType))
            ->addProperty('name', $string->setMax(20)->enum(['a', 'b']))
            ->addProperty('ordinary', new IntegerType);
        $document = OpenApi::make('3.1.0')->setInfo(new InfoObject('Business response'));
        $reference = $document->components->addSchema('Payload', Schema::fromType($type));
        $document->components->addSchema('Container', Schema::fromType((new ObjectType)->addProperty('payload', $reference)));

        $documents = [$document->toArray()];
        if (method_exists($document, 'serializeAs32')) {
            $documents[] = $document->serializeAs32();
        }

        foreach ($documents as $serialized) {
            $properties = $serialized['components']['schemas']['Payload']['properties'];
            $this->assertSame(true, $properties['nested']['properties']['id']['readOnly']);
            $this->assertSame(true, $properties['name']['readOnly']);
            $this->assertSame(20, $properties['name']['maxLength']);
            $this->assertSame(['a', 'b'], $properties['name']['enum']);
            $this->assertArrayNotHasKey('readOnly', $properties['ordinary']);
            $this->assertSame('#/components/schemas/Payload', $serialized['components']['schemas']['Container']['properties']['payload']['$ref']);
        }
    }

    public function test_cloning_preserves_read_only_and_isolates_changes(): void
    {
        $original = (new ReadOnlyIntegerType)->setMin(1);
        $clone = $original->clone();

        $clone->setMin(2);

        $this->assertSame(1, $original->toArray()['minimum']);
        $this->assertSame(2, $clone->toArray()['minimum']);
        $this->assertSame(true, $clone->toArray()['readOnly']);
    }

    public function test_explicit_serializers_also_work_on_legacy_scramble(): void
    {
        $type = (new ReadOnlyIntegerType)->setMin(1);

        $this->assertSame(['type' => 'integer', 'minimum' => 1, 'readOnly' => true], $type->serializeAs31());
        $this->assertSame(['type' => 'integer', 'minimum' => 1, 'readOnly' => true], $type->serializeAs32());
    }
}
