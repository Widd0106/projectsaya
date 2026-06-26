<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Character;
use App\Models\Message;
use App\Services\GeminiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(protected GeminiService $gemini)
    {
    }

    /**
     * Klik kartu karakter -> buka/buat ruang chat.
     * Jika ini chat baru, sistem otomatis menyimpan & menampilkan
     * 'Greeting' milik karakter sebagai pesan pembuka (poin 4).
     */
    public function show(Character $character): Response|RedirectResponse
    {
        if ($character->user_id !== Auth::id()) {
            abort(403);
        }

        $chat = Chat::firstOrCreate(
            ['user_id' => Auth::id(), 'character_id' => $character->id],
            ['last_message_at' => now()]
        );

        // Chat baru dan belum punya pesan sama sekali -> kirim greeting otomatis
        if ($chat->messages()->count() === 0) {
            Message::create([
                'chat_id' => $chat->id,
                'role' => 'assistant',
                'content' => $character->greeting,
            ]);

            $chat->update(['last_message_at' => now()]);
        }

        $allChats = $this->getRecentChatsForSidebar();

        return Inertia::render('ChatRoom', [
            'character' => $character,
            'chatId' => $chat->id,
            'messages' => $chat->messages()->get(['id', 'role', 'content', 'created_at']),
            'recentChats' => $allChats,
        ]);
    }

    /**
     * Endpoint AJAX (dipanggil via axios dari ChatRoom.vue) untuk
     * mengirim pesan baru dan menerima balasan AI secara real-time
     * tanpa reload halaman penuh.
     */
    public function sendMessage(Request $request, Chat $chat)
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        // Simpan pesan user dulu
        $userMessage = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        // Bangun system prompt + 10 riwayat terakhir, lalu panggil Gemini
        $aiReplyText = $this->gemini->generateReply($chat, $validated['message']);

        $aiMessage = Message::create([
            'chat_id' => $chat->id,
            'role' => 'assistant',
            'content' => $aiReplyText,
        ]);

        $chat->update(['last_message_at' => now()]);

        return response()->json([
            'userMessage' => $userMessage,
            'aiMessage' => $aiMessage,
        ]);
    }

    /**
     * Daftar 'Recent Chats' untuk sidebar kiri, diurutkan dari yang
     * paling baru dibalas. Search di sidebar (poin 5) difilter di
     * frontend berdasarkan nama karakter dari data ini.
     */
    protected function getRecentChatsForSidebar()
    {
        return Chat::where('user_id', Auth::id())
            ->with('character:id,name,avatar_path')
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function (Chat $chat) {
                $lastMessage = Message::where('chat_id', $chat->id)->latest()->first();

                return [
                    'chatId' => $chat->id,
                    'characterId' => $chat->character->id,
                    'characterName' => $chat->character->name,
                    'avatarUrl' => $chat->character->avatar_url,
                    'lastMessagePreview' => $lastMessage ? str($lastMessage->content)->limit(60)->toString() : '',
                    'lastMessageAt' => $chat->last_message_at,
                ];
            });
    }
}
