<?php

namespace Admin9\ScrambleExtensions\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Teaches Scramble what $this->success() / error() / deny() return.
 *
 * For paginators: returns the paginator type directly so Scramble's built-in
 * LengthAwarePaginatorTypeToSchema handles model type resolution.
 *
 * For other types: wraps in Generic<JsonResponse, [dataType, 200, []]>.
 *
 * The business envelope is added by BusinessResponseOperationExtension.
 */
class BusinessResponseInferExtension implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        $trait = config('scramble-extensions.response.trait');

        if (! $trait || ! trait_exists($trait)) {
            return false;
        }

        return in_array($trait, class_uses_recursive($type->name));
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        if (! in_array($event->name, ['success', 'error', 'deny'])) {
            return null;
        }

        // For error()/deny(): empty object wrapped in JsonResponse
        if ($event->name !== 'success') {
            return new Generic(JsonResponse::class, [
                new ObjectType('stdClass'),
                new LiteralIntegerType(200),
                new KeyedArrayType,
            ]);
        }

        $dataType = $event->getArg('data', 0, new ObjectType('stdClass'));

        // For paginators: return the paginator type directly.
        // Scramble's built-in TypeToSchema will handle model resolution.
        if ($dataType instanceof Generic && (
            $dataType->isInstanceOf(LengthAwarePaginator::class)
            || $dataType->isInstanceOf(Paginator::class)
        )) {
            return $dataType;
        }

        // For everything else: wrap in JsonResponse
        return new Generic(JsonResponse::class, [
            $dataType,
            new LiteralIntegerType(200),
            new KeyedArrayType,
        ]);
    }
}
