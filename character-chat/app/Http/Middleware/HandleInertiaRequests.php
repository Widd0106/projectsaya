<?php

namespace App\Http\Middleware;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data ini otomatis tersedia di SEMUA halaman Inertia/Vue
     * lewat usePage().props, supaya sidebar "Recent Chats" bisa
     * dirender konsisten di Dashboard, ChatRoom, dan form Create/Edit
     * tanpa setiap controller harus mengirim ulang data yang sama.
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => Auth::check() ? [
                    'id' => Auth::id(),
                    'username' => Auth::user()->username,
                ] : null,
            ],
            'sidebarRecentChats' => function () use ($request) {
                if (! Auth::check()) {
                    return [];
                }

                return Chat::where('user_id', Auth::id())
                    ->with('character:id,name,avatar_path')
                    ->whereNotNull('last_message_at')
                    ->orderByDesc('last_message_at')
                    ->limit(20)
                    ->get()
                    ->map(function (Chat $chat) {
                        $lastMessage = Message::where('chat_id', $chat->id)->latest()->first();

                        return [
                            'chatId' => $chat->id,
                            'characterId' => $chat->character->id,
                            'characterName' => $chat->character->name,
                            'avatarUrl' => $chat->character->avatar_url,
                            'lastMessagePreview' => $lastMessage ? str($lastMessage->content)->limit(60)->toString() : '',
                        ];
                    });
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
