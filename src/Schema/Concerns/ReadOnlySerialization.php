<?php

namespace Admin9\ScrambleExtensions\Schema\Concerns;

/**
 * Adds the standard readOnly keyword to explicit Scramble type subclasses.
 */
trait ReadOnlySerialization
{
    public function toArray(): array
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): mixed
    {
        $schema = method_exists(parent::class, 'serializeAs31')
            ? parent::serializeAs31()
            : parent::toArray();

        return [...(array) $schema, 'readOnly' => true];
    }

    public function serializeAs32(): mixed
    {
        $schema = method_exists(parent::class, 'serializeAs32')
            ? parent::serializeAs32()
            : parent::toArray();

        return [...(array) $schema, 'readOnly' => true];
    }
}
