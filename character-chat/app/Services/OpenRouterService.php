<?php

namespace App\Services;

use App\Models\Chat;
use App\Services\Concerns\BuildsCharacterSystemPrompt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenRouterService
{
    use BuildsCharacterSystemPrompt;

    protected string $apiKey;

    /** Model utama (index 0) + urutan model cadangan kalau model sebelumnya gagal/limit. */
    protected array $models;

    protected string $baseUrl = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');
        $this->models = config('services.openrouter.models', ['google/gemini-2.5-flash']);
    }

    /**
     * Sama seperti GeminiService::generateReply, tapi lewat OpenRouter
     * (format pesan gaya OpenAI: array of {role, content}).
     *
     * OpenRouter menerima parameter "models" (array). Kalau model pertama
     * gagal/kena limit/di-deprecate, OpenRouter otomatis coba model
     * berikutnya di daftar itu tanpa perlu logic tambahan di sisi kita.
     *
     * Default config pakai model ber-akhiran ":free" / "openrouter/free"
     * (100% gratis, tanpa kartu kredit) — cocok kalau belum mau keluar biaya.
     */
    public function generateReply(Chat $chat, string $userMessage): string
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('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');
        }

        $character = $chat->character;
        $systemPrompt = $this->buildSystemPrompt($character);

        $recentMessages = $chat->recentMessages(10)->get()->reverse()->values();

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($recentMessages as $message) {
            $messages[] = [
                'role' => $message->role === 'user' ? 'user' : 'assistant',
                'content' => $message->content,
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = [
            // Model pertama di daftar = model utama yang dicoba lebih dulu.
            'model' => $this->models[0],
            'models' => $this->models,
            'messages' => $messages,
            'temperature' => 0.85,
            'top_p' => 0.9,
            'max_tokens' => 600,
        ];

        $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                // Header opsional yang direkomendasikan OpenRouter untuk analytics/rate-limit mereka.
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ])
            ->timeout(30)
            ->retry(2, 300, function ($exception, $request) {
                if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                    return true;
                }

                $status = $exception->response?->status();

                return $status === 429 || ($status !== null && $status >= 500);
            }, throw: false)
            ->post($this->baseUrl, $payload);

        if ($response->failed()) {
            Log::error('OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                'Gagal menghubungi OpenRouter API: ' . $response->status() . ' - ' . $response->body()
            );
        }

        $data = $response->json();

        $text = $data['choices'][0]['message']['content'] ?? null;

        if (! $text) {
            $finishReason = $data['choices'][0]['finish_reason'] ?? 'UNKNOWN';
            Log::warning('OpenRouter returned empty content', ['finishReason' => $finishReason, 'data' => $data]);

            return "Maaf, aku sedang tidak bisa merespons pesan itu. Coba ungkapkan dengan cara lain?";
        }

        return trim($text);
    }
}