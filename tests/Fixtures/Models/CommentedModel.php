<?php

namespace Admin9\ScrambleExtensions\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class CommentedModel extends Model
{
    public function getConnection()
    {
        return new class
        {
            public function getDriverName(): string
            {
                return 'sqlite';
            }

            public function getSchemaBuilder(): object
            {
                return new class
                {
                    public function getColumns(string $table): array
                    {
                        return [
                            [
                                'name' => 'id',
                                'type' => 'integer',
                                'auto_increment' => true,
                                'nullable' => false,
                                'default' => null,
                                'comment' => 'Identifier',
                            ],
                            [
                                'name' => 'name',
                                'type' => 'varchar',
                                'auto_increment' => false,
                                'nullable' => false,
                                'default' => null,
                                'comment' => 'Display name',
                            ],
                        ];
                    }

                    public function getIndexes(string $table): array
                    {
                        return [];
                    }
                };
            }
        };
    }
}
