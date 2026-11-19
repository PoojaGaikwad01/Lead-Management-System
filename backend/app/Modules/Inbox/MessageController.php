<?php

namespace App\Modules\Inbox;

use App\Http\Controllers\Controller;
use App\Modules\Leads\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    public function index(Lead $lead): AnonymousResourceCollection
    {
        Gate::authorize('view', $lead);

        $messages = $lead->messages()->latest('sent_at')->latest('id')->limit(500)->get()->reverse()->values();

        return MessageResource::collection($messages);
    }

    public function store(Request $request, Lead $lead, MessageSender $sender): JsonResponse
    {
        Gate::authorize('reply', $lead);

        $data = $request->validate([
            'text' => ['nullable', 'required_without:file', 'string', 'max:4096'],
            'file' => ['nullable', 'file', 'max:16384', 'mimes:jpg,jpeg,png,webp,gif,mp3,ogg,m4a,aac,wav,amr,opus,mp4,pdf,xls,xlsx,csv,doc,docx'],
        ]);

        $message = $sender->send($lead, $data['text'] ?? null, $request->file('file'));
        $resource = new MessageResource($message);

        return $message->status === 'failed'
            ? response()->json(['message' => 'Could not send the WhatsApp message. Please try again.', 'data' => $resource], 502)
            : $resource->response()->setStatusCode(201);
    }

    public function media(Request $request, Message $message): StreamedResponse
    {
        $user = $request->user();
        abort_unless($message->lead ? $user->can('view', $message->lead) : $user->hasAdminAccess(), 403);
        abort_unless($message->media_url && Storage::disk('local')->exists($message->media_url), 404);

        $disk = Storage::disk('local');

        return $request->boolean('download')
            ? $disk->download($message->media_url, $message->media_name)
            : $disk->response($message->media_url, $message->media_name);
    }
}
