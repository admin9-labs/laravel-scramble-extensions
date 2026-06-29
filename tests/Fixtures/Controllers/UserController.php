<?php

namespace Admin9\ScrambleExtensions\Tests\Fixtures\Controllers;

use Admin9\ScrambleExtensions\Tests\Fixtures\Filters\UserFilter;
use Admin9\ScrambleExtensions\Tests\Fixtures\Models\User;
use Admin9\ScrambleExtensions\Tests\Fixtures\Requests\UserRequest;
use Illuminate\Routing\Controller;
use Mitoop\Http\RespondsWithJson;

class UserController extends Controller
{
    use RespondsWithJson;

    public function show(User $user): mixed
    {
        return $this->success($user);
    }

    public function index(): mixed
    {
        $users = User::query()->filter(UserFilter::class)->paginate();

        return $this->success($users);
    }

    public function store(UserRequest $request): mixed
    {
        return $this->success($request->validated());
    }

    public function update(UserRequest $request, User $user): mixed
    {
        return $this->success($request->validated());
    }
}
