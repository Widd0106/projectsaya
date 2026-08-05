<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Services\AiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiChatController extends Controller
{
    public function __construct(protected AiService $aiService)
    {
    }

    public function index(Request $request): Response
    {
        $chats = $request->user()->chats()->latest()->get(['id', 'title', 'created_at']);

        return Inertia::render('AiChat/Index', [
            'chats' => $chats,
        ]);
    }

    public function show(Chat $chat)
    {
        abort_unless($chat->user_id === auth()->id(), 403);

        return response()->json([
            'chat' => $chat,
            'messages' => $chat->messages,
        ]);
    }

    public function send(Request $request)
{
    $validated = $request->validate([
        'chat_id' => 'nullable|exists:chats,id',
        'message' => 'required|string|max:2000',
    ]);

    $user = $request->user();

    $chat = $validated['chat_id']
        ? $user->chats()->findOrFail($validated['chat_id'])
        : $user->chats()->create(['title' => \Illuminate\Support\Str::limit($validated['message'], 40)]);

    $history = $chat->messages()
        ->orderBy('created_at')
        ->latest()
        ->limit(20)
        ->get(['role', 'content'])
        ->sortBy('created_at')
        ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
        ->values()
        ->toArray();

    $chat->messages()->create([
        'role' => 'user',
        'content' => $validated['message'],
    ]);

    $result = $this->aiService->ask($validated['message'], $user, $history);

    $chat->messages()->create([
        'role' => 'assistant',
        'content' => $result['answer'],
        'ai_provider' => $result['provider'],
    ]);

    return response()->json([
        'chat_id' => $chat->id,
        'chat_title' => $chat->title,
        'answer' => $result['answer'],
        'provider' => $result['provider'],
    ]);
}

    public function search(Request $request)
    {
        $keyword = $request->query('q', '');

        $chats = $request->user()->chats()
            ->when($keyword, fn ($q) => $q->where('title', 'like', "%{$keyword}%"))
            ->latest()
            ->get(['id', 'title', 'created_at']);

        return response()->json($chats);
    }
}