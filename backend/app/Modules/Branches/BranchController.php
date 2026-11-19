<?php

namespace App\Modules\Branches;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Branch::class);

        return response()->json(['data' => Branch::withCount('users')->orderBy('name')->get()]);
    }

    public function store(BranchRequest $request): JsonResponse
    {
        return response()->json(['data' => Branch::create($request->validated())], 201);
    }

    public function update(BranchRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($request->validated());

        return response()->json(['data' => $branch]);
    }

    public function destroy(Branch $branch): Response
    {
        Gate::authorize('delete', $branch);
        $branch->delete();

        return response()->noContent();
    }
}
