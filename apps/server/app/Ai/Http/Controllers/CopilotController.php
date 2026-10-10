<?php

namespace App\Ai\Http\Controllers;

use App\Ai\Actions\CopilotConversations;
use App\Ai\Actions\ReplyToCopilot;
use App\Ai\Http\Resources\CopilotConversationResource;
use App\Ai\Http\Resources\CopilotMessageResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CopilotController extends Controller
{
    public function __construct(private readonly CopilotConversations $conversations)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return CopilotConversationResource::collection(
            $this->conversations->ownedBy($this->authenticatedUser($request))
                ->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)
        );
    }

    public function store(Request $request): CopilotConversationResource
    {
        return new CopilotConversationResource($this->conversations->start($this->authenticatedUser($request)));
    }

    public function messages(Request $request, string $conversation): AnonymousResourceCollection
    {
        $chat = $this->conversations->find($this->authenticatedUser($request), $conversation);

        return CopilotMessageResource::collection(
            $chat->chatMessages()->whereIn('role', ['user', 'assistant'])
                ->orderByDesc('created_at')->orderByDesc('id')->paginate(30)
        );
    }

    public function reply(Request $request, string $conversation, ReplyToCopilot $reply): StreamedResponse
    {
        $request->validate(['message' => ['required', 'string']]);
        $events = $reply(
            $this->authenticatedUser($request),
            $conversation,
            $request->string('message')->trim()->toString(),
            fn (): bool => connection_aborted() === 1,
        );

        return response()->stream(function () use ($events): void {
            // Keep the worker alive long enough to persist interruption and release its lock.
            $previous = ignore_user_abort(true);
            try {
                echo ": connected\n\n";
                $this->flushOutput();
                foreach ($events as $event) {
                    echo 'data: ' . json_encode($event, JSON_THROW_ON_ERROR) . "\n\n";
                    $this->flushOutput();
                }
            } finally {
                ignore_user_abort((bool) $previous);
            }
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-store, private',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function destroy(Request $request, string $conversation): Response
    {
        $this->conversations->remove($this->authenticatedUser($request), $conversation);

        return response()->noContent();
    }

    private function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
