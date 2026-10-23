<?php

namespace Admin9\ScrambleExtensions\Schema\Types;

use Admin9\ScrambleExtensions\Schema\Concerns\ReadOnlySerialization;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;

class ReadOnlyIntegerType extends IntegerType
{
    use ReadOnlySerialization;
}
