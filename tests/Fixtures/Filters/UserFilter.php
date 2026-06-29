<?php

namespace Admin9\ScrambleExtensions\Tests\Fixtures\Filters;

use Mitoop\LaravelQueryBuilder\AbstractFilter;

class UserFilter extends AbstractFilter
{
    protected array $allowedSorts = ['created_at', 'name'];

    public function rules(): array
    {
        return [
            'name',
            'email',
            'status' => 'nullable|string',
        ];
    }
}
