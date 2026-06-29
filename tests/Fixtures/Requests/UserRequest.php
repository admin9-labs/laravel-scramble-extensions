<?php

namespace Admin9\ScrambleExtensions\Tests\Fixtures\Requests;

use Mitoop\LaravelEfficientFormRequest\EfficientSceneFormRequest;

class UserRequest extends EfficientSceneFormRequest
{
    public function storeRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ];
    }

    public function updateRules(): array
    {
        return [
            'name' => 'string|max:255',
        ];
    }
}
