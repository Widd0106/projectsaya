<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Character;
use App\Models\Message;
use App\Services\AiReplyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(protected AiReplyService $ai)
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
     * Endpoint AJAX non-streaming (dipertahankan untuk kompatibilitas /
     * dipakai kalau browser tidak mendukung ReadableStream). Frontend
     * utama sekarang pakai streamMessage() di bawah.
     */
    public function sendMessage(Request $request, Chat $chat)
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $userMessage = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        $aiReplyText = $this->ai->generateReply($chat);

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
     * Endpoint streaming (SSE) — dipanggil via fetch() dari ChatRoom.vue.
     * Simpan pesan user, lalu stream balasan AI kata-per-kata.
     */
    public function streamMessage(Request $request, Chat $chat): StreamedResponse
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $userMessage = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        $chat->update(['last_message_at' => now()]);

        return $this->streamAiResponse($chat, ['type' => 'user_message', 'message' => $userMessage]);
    }

    /**
     * Regenerate balasan AI TERAKHIR (satu-satunya yang diizinkan, supaya
     * riwayat chat tetap konsisten & tidak butuh percabangan versi pesan).
     */
    public function regenerateMessage(Chat $chat, Message $message): StreamedResponse
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }
        if ($message->chat_id !== $chat->id) {
            abort(403);
        }
        if ($message->role !== 'assistant') {
            abort(422, 'Cuma balasan AI yang bisa di-regenerate.');
        }

        $latest = $chat->messages()->latest('id')->first();
        if (! $latest || $latest->id !== $message->id) {
            abort(422, 'Cuma balasan terakhir yang bisa di-regenerate.');
        }

        $replacedId = $message->id;
        $message->delete();

        return $this->streamAiResponse($chat, ['type' => 'replacing', 'replacedMessageId' => $replacedId]);
    }

    /**
     * Edit isi pesan USER. Semua pesan setelahnya (termasuk balasan AI lama)
     * dihapus supaya riwayat tetap konsisten, lalu AI generate ulang
     * balasan berdasarkan isi pesan yang sudah diedit.
     */
    public function editMessage(Request $request, Chat $chat, Message $message): StreamedResponse
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }
        if ($message->chat_id !== $chat->id) {
            abort(403);
        }
        if ($message->role !== 'user') {
            abort(422, 'Cuma pesan kamu sendiri yang bisa diedit.');
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $removedIds = $chat->messages()->where('id', '>', $message->id)->pluck('id');
        $chat->messages()->where('id', '>', $message->id)->delete();

        $message->update(['content' => $validated['message']]);
        $chat->update(['last_message_at' => now()]);

        return $this->streamAiResponse($chat, [
            'type' => 'edited',
            'message' => $message->fresh(),
            'removedMessageIds' => $removedIds,
        ]);
    }

    /**
     * Inti logic streaming (SSE) yang dipakai bareng oleh streamMessage,
     * regenerateMessage, dan editMessage. $initialEvent (opsional) dikirim
     * duluan sebelum delta AI mulai mengalir, supaya frontend tahu perlu
     * reconcile apa (pesan user baru / pesan yang diganti / pesan yang diedit).
     */
    protected function streamAiResponse(Chat $chat, ?array $initialEvent = null): StreamedResponse
    {
        return response()->stream(function () use ($chat, $initialEvent) {
            $send = function (array $payload) {
                echo 'data: ' . json_encode($payload) . "\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();
            };

            if ($initialEvent) {
                $send($initialEvent);
            }

            $accumulated = '';

            try {
                $this->ai->streamReply($chat, function (string $piece) use (&$accumulated, $send) {
                    $accumulated .= $piece;
                    $send(['type' => 'delta', 'delta' => $piece]);
                });
            } catch (\Throwable $e) {
                Log::error('Stream AI gagal total.', ['error' => $e->getMessage()]);

                if ($accumulated === '') {
                    $accumulated = "Duh, koneksi ke server AI-nya lagi bermasalah nih. Coba kirim pesannya lagi sebentar lagi ya.";
                    $send(['type' => 'delta', 'delta' => $accumulated]);
                }
            }

            $aiMessage = Message::create([
                'chat_id' => $chat->id,
                'role' => 'assistant',
                'content' => $accumulated,
            ]);

            $chat->update(['last_message_at' => now()]);

            $send(['type' => 'done', 'aiMessage' => $aiMessage]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
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

    public function destroyMessage(Chat $chat, Message $message)
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }
        if ($message->chat_id !== $chat->id) {
            abort(403);
        }

        $message->delete();

        $latestRemaining = $chat->messages()->latest()->first();
        $chat->update(['last_message_at' => $latestRemaining?->created_at]);

        return response()->json(['deleted' => true]);
    }

    public function clear(Chat $chat)
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        // Hapus semua pesan di sesi chat ini
        $chat->messages()->delete();

        // Setelah dihapus, otomatis kirim ulang greeting karakter
        // supaya chat tidak kosong total, langsung mulai dari awal
        $greetingMessage = Message::create([
            'chat_id' => $chat->id,
            'role'    => 'assistant',
            'content' => $chat->character->greeting,
        ]);

        $chat->update(['last_message_at' => now()]);

        return response()->json(['greetingMessage' => $greetingMessage]);
    }
}