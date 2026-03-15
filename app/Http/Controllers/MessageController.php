<?php

namespace App\Http\Controllers;

use App\Contracts\Services\MessageServiceInterface;
use App\Http\Requests\SendMessageRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageServiceInterface $messageService,
    ) {}

    /**
     * Show conversation with the given user and mark received messages as read.
     */
    public function index(Request $request, User $user): View|RedirectResponse
    {
        $authUser = $request->user();

        if ($authUser->id === $user->id) {
            return redirect()->route('users.index');
        }

        $this->messageService->markMessagesAsRead($authUser->id, $user->id);

        $messages = $this->messageService->getConversation($authUser->id, $user->id);

        return view('messages.show', [
            'recipient' => $user,
            'messages' => $messages,
            'authUser' => $authUser,
        ]);
    }

    /**
     * Store a new message and broadcast it via WebSocket.
     */
    public function store(SendMessageRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $message = $this->messageService->send(
            senderId: $request->user()->id,
            receiverId: $user->id,
            body: $request->body,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $message->id,
                'created_at' => $message->created_at->toIso8601String(),
            ]);
        }

        return redirect()->route('messages.show', $user);
    }

    /**
     * Mark all messages from the given sender as read for the authenticated user.
     */
    public function markRead(Request $request, User $user): JsonResponse
    {
        $this->messageService->markMessagesAsRead($request->user()->id, $user->id);

        return response()->json(['ok' => true]);
    }

    /**
     * Return the count of unread messages for the authenticated user.
     */
    public function unread(Request $request): JsonResponse
    {
        $unread = $this->messageService->getUnreadMessages($request->user()->id);

        return response()->json([
            'count' => $unread->count(),
        ]);
    }
}
