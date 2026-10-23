<?php

namespace Admin9\ScrambleExtensions\Tests\Schema;

use Admin9\ScrambleExtensions\Schema\Concerns\ReadOnlySerialization;
use Admin9\ScrambleExtensions\Tests\TestCase;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use PHPUnit\Framework\Attributes\DataProvider;

class ReadOnlySerializationTest extends TestCase
{
    #[DataProvider('serializers')]
    public function test_mixed_type_serializes_as_an_unconstrained_read_only_schema(string $serializer): void
    {
        $type = new class extends MixedType
        {
            use ReadOnlySerialization;
        };

        $this->assertSame(['readOnly' => true], $type->$serializer());
    }

    public static function serializers(): array
    {
        return [
            'legacy' => ['toArray'],
            'OpenAPI 3.1' => ['serializeAs31'],
            'OpenAPI 3.2' => ['serializeAs32'],
        ];
    }

    public function test_read_only_mixed_fields_survive_full_document_serialization(): void
    {
        $mixed = new class extends MixedType
        {
            use ReadOnlySerialization;
        };
        $type = (new ObjectType)
            ->addProperty('value', $mixed)
            ->addProperty('ordinary', new MixedType);
        $document = OpenApi::make('3.1.0')->setInfo(new InfoObject('Business response'));
        $document->components->addSchema('Payload', Schema::fromType($type));

        foreach (['toArray', 'serializeAs31', 'serializeAs32'] as $serializer) {
            if (! method_exists($document, $serializer)) {
                continue;
            }

            $serialized = $document->$serializer();
            $properties = $serialized['components']['schemas']['Payload']['properties'];
            $this->assertSame(['readOnly' => true], $properties['value']);
            $this->assertSame('{}', json_encode($properties['ordinary'], JSON_THROW_ON_ERROR));
        }
    }
}
