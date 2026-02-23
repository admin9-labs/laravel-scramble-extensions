<?php

namespace Admin9\ScrambleExtensions\Extensions;

use Dedoc\Scramble\Infer\Extensions\Event\PropertyFetchEvent;
use Dedoc\Scramble\Infer\Extensions\PropertyTypeExtension;
use Dedoc\Scramble\Support\InferExtensions\ModelExtension;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\Eloquent\Model;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;

/**
 * Reads database column comments and attaches them as OpenAPI property descriptions.
 *
 * When a column has a comment (via migration ->comment('...')), this extension
 * delegates to ModelExtension for the base type, then attaches a docNode so
 * TypeTransformer sets the OpenAPI description automatically.
 *
 * Columns without comments return null, letting ModelExtension handle them normally.
 */
class ModelColumnDescriptionExtension implements PropertyTypeExtension
{
    private static array $columnCache = [];

    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(Model::class);
    }

    public function getPropertyType(PropertyFetchEvent $event): ?Type
    {
        $comment = $this->getColumnComment(
            $event->getInstance()->name,
            $event->getName()
        );

        if (! $comment) {
            return null;
        }

        $baseType = (new ModelExtension)->getPropertyType($event);
        if (! $baseType) {
            return null;
        }

        $docNode = new PhpDocNode([new PhpDocTextNode($comment)]);
        PhpDoc::addSummaryAttributes($docNode);
        $baseType->setAttribute('docNode', $docNode);

        return $baseType;
    }

    private function getColumnComment(string $modelClass, string $columnName): ?string
    {
        $columns = $this->getColumns($modelClass);

        return $columns[$columnName] ?? null;
    }

    private function getColumns(string $modelClass): array
    {
        if (isset(self::$columnCache[$modelClass])) {
            return self::$columnCache[$modelClass];
        }

        try {
            $model = app()->make($modelClass);
            $rawColumns = $model->getConnection()
                ->getSchemaBuilder()
                ->getColumns($model->getTable());

            $comments = [];
            foreach ($rawColumns as $col) {
                if (! empty($col['comment'])) {
                    $comments[$col['name']] = $col['comment'];
                }
            }

            return self::$columnCache[$modelClass] = $comments;
        } catch (\Throwable) {
            return self::$columnCache[$modelClass] = [];
        }
    }

    public static function resetCache(): void
    {
        self::$columnCache = [];
    }
}
