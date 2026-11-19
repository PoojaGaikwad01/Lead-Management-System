<?php

namespace App\Modules\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        return response()->json(['data' => User::with('branch')->orderBy('name')->get()]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = User::create($request->userData());

        return response()->json(['data' => $user->load('branch')], 201);
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = $request->userData();
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $user->update($data);

        return response()->json(['data' => $user->load('branch')]);
    }

    public function destroy(User $user): Response
    {
        Gate::authorize('delete', $user);
        $user->delete();

        return response()->noContent();
    }
}
