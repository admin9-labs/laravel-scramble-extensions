<?php

namespace Mitoop\Http;

trait RespondsWithJson
{
    public function success(mixed $data = null): mixed
    {
        return $data;
    }

    public function error(): mixed
    {
        return null;
    }

    public function deny(): mixed
    {
        return null;
    }
}
