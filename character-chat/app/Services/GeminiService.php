<?php

namespace App\Services;

use App\Models\Chat;
use App\Services\Concerns\BuildsCharacterSystemPrompt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiService
{
    use BuildsCharacterSystemPrompt;

    protected string $apiKey;
    protected string $model;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-2.5-flash-lite');
    }

    /**
     * Bangun System Prompt dari profil karakter + contoh dialog,
     * lalu kirim ke Gemini bersama 10 riwayat chat terakhir sebagai
     * konteks percakapan (Inti dari poin 6 - Prompt Engineering).
     */
    public function generateReply(Chat $chat, string $userMessage): string
    {
        $character = $chat->character;

        $systemPrompt = $this->buildSystemPrompt($character);

        // Ambil 10 pesan terakhir (dari yang paling baru), lalu balik urutannya
        // jadi kronologis (lama -> baru) supaya AI membaca konteks dengan benar.
        $recentMessages = $chat->recentMessages(10)->get()->reverse()->values();

        $contents = [];

        foreach ($recentMessages as $message) {
            $contents[] = [
                'role' => $message->role === 'user' ? 'user' : 'model',
                'parts' => [['text' => $message->content]],
            ];
        }

        // Tambahkan pesan baru dari user yang sedang dikirim sekarang
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.85,
                'topP' => 0.9,
                'maxOutputTokens' => 600,
            ],
        ];

        if (empty($this->apiKey)) {
            throw new RuntimeException('AQ.Ab8RN6JWthHUVee35U2oi6lMB4Hre8_qYB_2Jhq7hE0bXvg1Kw');
        }

        // Retry otomatis 2x (total 3 percobaan) khusus untuk error yang sifatnya
        // sementara (timeout, 5xx, 429 rate limit) dengan jeda 300ms, 600ms.
        // Error 4xx lain (401/400, dst) tidak perlu diulang karena pasti gagal lagi.
        $response = Http::timeout(30)
            ->retry(2, 300, function ($exception, $request) {
                if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                    return true;
                }

                $status = $exception->response?->status();

                return $status === 429 || ($status !== null && $status >= 500);
            }, throw: false)
            ->post(
                "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}",
                $payload
            );

        if ($response->failed()) {
            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                'Gagal menghubungi Gemini API: ' . $response->status() . ' - ' . $response->body()
            );
        }

        $data = $response->json();

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! $text) {
            // Kemungkinan diblokir oleh safety filter Gemini
            $finishReason = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            Log::warning('Gemini returned empty content', ['finishReason' => $finishReason, 'data' => $data]);

            return "Maaf, aku sedang tidak bisa merespons pesan itu. Coba ungkapkan dengan cara lain?";
        }

        return trim($text);
    }
}