<?php

namespace App\Services;

use App\Models\Chat;
use App\Services\Concerns\BuildsCharacterSystemPrompt;
use App\Services\Concerns\ConsumesSseStream;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenRouterService
{
    use BuildsCharacterSystemPrompt, ConsumesSseStream;

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
     * Susun payload pesan gaya OpenAI (array of {role, content}) dari
     * system prompt karakter + 10 riwayat chat terakhir. Riwayat ini
     * SUDAH termasuk pesan user paling baru (karena controller selalu
     * menyimpan pesan user ke DB sebelum memanggil AI), jadi tidak perlu
     * ditambahkan lagi secara terpisah (dulu sempat dobel, sekarang dibenahi).
     */
    protected function buildMessages(Chat $chat): array
    {
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

        return $messages;
    }

    protected function basePayload(Chat $chat): array
    {
        return [
            // Model pertama di daftar = model utama yang dicoba lebih dulu.
            // Kalau gagal/limit, OpenRouter otomatis coba model berikutnya di "models".
            'model' => $this->models[0],
            'models' => $this->models,
            'messages' => $this->buildMessages($chat),
            'temperature' => 0.85,
            'top_p' => 0.9,
            'max_tokens' => 600,
        ];
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            // Header opsional yang direkomendasikan OpenRouter untuk analytics/rate-limit mereka.
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ];
    }

    protected function retryWhen(): \Closure
    {
        return function ($exception, $request) {
            if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                return true;
            }

            $status = $exception->response?->status();

            return $status === 429 || ($status !== null && $status >= 500);
        };
    }

    /**
     * Balasan non-streaming (dipakai sebagai fallback terakhir kalau butuh respons utuh sekaligus).
     */
    public function generateReply(Chat $chat): string
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('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');
        }

        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->retry(2, 300, $this->retryWhen(), throw: false)
            ->post($this->baseUrl, $this->basePayload($chat));

        if ($response->failed()) {
            Log::error('OpenRouter API error', ['status' => $response->status(), 'body' => $response->body()]);

            throw new RuntimeException('Gagal menghubungi OpenRouter API: ' . $response->status() . ' - ' . $response->body());
        }

        $text = $response->json('choices.0.message.content');

        if (! $text) {
            Log::warning('OpenRouter returned empty content', ['data' => $response->json()]);

            return "Maaf, aku sedang tidak bisa merespons pesan itu. Coba ungkapkan dengan cara lain?";
        }

        return trim($text);
    }

    /**
     * Balasan streaming. $onChunk dipanggil setiap ada potongan teks baru
     * (delta) dari model. Mengembalikan teks lengkap di akhir (buat disimpan ke DB).
     */
    public function streamReply(Chat $chat, callable $onChunk): string
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('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');
        }

        $payload = $this->basePayload($chat);
        $payload['stream'] = true;

        $response = Http::withHeaders($this->headers())
            ->withOptions(['stream' => true])
            // Model gratis kadang lambat, kasih ruang lebih daripada versi non-stream.
            ->timeout(120)
            ->post($this->baseUrl, $payload);

        if ($response->failed()) {
            Log::error('OpenRouter API error (stream)', ['status' => $response->status()]);

            throw new RuntimeException('Gagal menghubungi OpenRouter API (stream): ' . $response->status());
        }

        $fullText = '';

        $this->consumeSse($response->getBody(), function (string $data) use (&$fullText, $onChunk) {
            $json = json_decode($data, true);
            $piece = $json['choices'][0]['delta']['content'] ?? null;

            if ($piece !== null && $piece !== '') {
                $fullText .= $piece;
                $onChunk($piece);
            }
        });

        if ($fullText === '') {
            $fallback = "Maaf, aku sedang tidak bisa merespons pesan itu. Coba ungkapkan dengan cara lain?";
            $onChunk($fallback);

            return $fallback;
        }

        return trim($fullText);
    }
}