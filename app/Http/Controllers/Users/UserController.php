<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Domain\Users\Models\User;
use App\Domain\Users\Actions\CreateUserAction;
use App\Domain\Users\Actions\UpdateUserAction;
use App\Domain\Users\Actions\ChangeUserStatusAction;
use App\Domain\Users\Actions\ChangeUserRoleAction;
use App\Domain\Users\Repositories\EloquentUserRepository;
use App\Domain\Users\Requests\StoreUserRequest;
use App\Domain\Users\Requests\UpdateUserRequest;
use App\Domain\Users\Requests\ChangeStatusRequest;
use App\Domain\Users\Resources\UserResource;

class UserController extends Controller
{
    public function index()
    {
        return UserResource::collection(User::all());
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return new UserResource($user);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action)
    {
        $user = $action->execute($request->toDto());
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, $id, UpdateUserAction $action)
    {
        $eloquentRepo = new EloquentUserRepository();
        $user = $eloquentRepo->findById($id);
        $updated = $action->execute($user, $request->toDto());

        return new UserResource($updated);
    }

    public function changeStatus(ChangeStatusRequest $request, $id, ChangeUserStatusAction $action)
    {
        $eloquentRepo = new EloquentUserRepository();
        $user = $eloquentRepo->findById($id);
        $updated = $action->execute($user, $request->is_active);

        return new UserResource($updated);
    }

    public function changeRole($id, ChangeUserRoleAction $action)
    {
        $user = User::findOrFail($id);

        request()->validate([
            'role' => 'required|in:admin,publisher,user'
        ]);

        $updated = $action->execute($user, request('role'));

        return new UserResource($updated);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
