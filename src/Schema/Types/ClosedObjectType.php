<?php

namespace Admin9\ScrambleExtensions\Schema\Types;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use LogicException;

class ClosedObjectType extends ObjectType
{
    public function toArray(): array
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): mixed
    {
        $this->ensureNoAdditionalPropertiesSchema();

        $schema = method_exists(parent::class, 'serializeAs31')
            ? parent::serializeAs31()
            : parent::toArray();

        return [...$schema, 'additionalProperties' => false];
    }

    public function serializeAs32(): mixed
    {
        $this->ensureNoAdditionalPropertiesSchema();

        $schema = method_exists(parent::class, 'serializeAs32')
            ? parent::serializeAs32()
            : parent::toArray();

        return [...$schema, 'additionalProperties' => false];
    }

    private function ensureNoAdditionalPropertiesSchema(): void
    {
        if ($this->additionalProperties !== null) {
            throw new LogicException('Closed objects cannot have a typed additional-properties schema.');
        }
    }
}
