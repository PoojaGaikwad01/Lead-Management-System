<?php

namespace App\Modules\Workflow;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $user->notifications()->latest()->limit(30)->get()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                ...$n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request): Response
    {
        $ids = $request->validate(['ids' => ['nullable', 'array'], 'ids.*' => ['string']])['ids'] ?? null;

        $request->user()->unreadNotifications()
            ->when($ids, fn ($query) => $query->whereIn('id', $ids))
            ->update(['read_at' => now()]);

        return response()->noContent();
    }
}
