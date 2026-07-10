<?php

namespace App\Services;

use App\Models\Chat;
use App\Services\Concerns\BuildsCharacterSystemPrompt;
use App\Services\Concerns\ConsumesSseStream;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiService
{
    use BuildsCharacterSystemPrompt, ConsumesSseStream;

    protected string $apiKey;
    protected string $model;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-2.5-flash-lite');
    }

    /**
     * Susun system prompt + riwayat percakapan (format Gemini: contents[]
     * berisi role user/model). Riwayat 10 pesan terakhir SUDAH termasuk
     * pesan user paling baru (sudah disimpan ke DB sebelum method ini
     * dipanggil), jadi tidak perlu ditambahkan lagi secara terpisah.
     */
    protected function buildContents(Chat $chat): array
    {
        $character = $chat->character;
        $systemPrompt = $this->buildSystemPrompt($character);

        $recentMessages = $chat->recentMessages(10)->get()->reverse()->values();

        $contents = [];

        foreach ($recentMessages as $message) {
            $contents[] = [
                'role' => $message->role === 'user' ? 'user' : 'model',
                'parts' => [['text' => $message->content]],
            ];
        }

        return [$systemPrompt, $contents];
    }

    protected function basePayload(Chat $chat): array
    {
        [$systemPrompt, $contents] = $this->buildContents($chat);

        return [
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
     * Balasan non-streaming — dipakai sebagai provider cadangan (fallback)
     * kalau OpenRouter gagal total.
     */
    public function generateReply(Chat $chat): string
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('AQ.Ab8RN6JWthHUVee35U2oi6lMB4Hre8_qYB_2Jhq7hE0bXvg1Kw');
        }

        $response = Http::timeout(30)
            ->retry(2, 300, $this->retryWhen(), throw: false)
            ->post(
                "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}",
                $this->basePayload($chat)
            );

        if ($response->failed()) {
            Log::error('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);

            throw new RuntimeException('Gagal menghubungi Gemini API: ' . $response->status() . ' - ' . $response->body());
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! $text) {
            $finishReason = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            Log::warning('Gemini returned empty content', ['finishReason' => $finishReason, 'data' => $data]);

            return "Maaf, aku sedang tidak bisa merespons pesan itu. Coba ungkapkan dengan cara lain?";
        }

        return trim($text);
    }

    /**
     * Balasan streaming lewat endpoint streamGenerateContent (SSE).
     * $onChunk dipanggil tiap ada potongan teks baru dari model.
     */
    public function streamReply(Chat $chat, callable $onChunk): string
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('AQ.Ab8RN6JWthHUVee35U2oi6lMB4Hre8_qYB_2Jhq7hE0bXvg1Kw');
        }

        $response = Http::withOptions(['stream' => true])
            // Model gratis kadang lambat, kasih ruang lebih daripada versi non-stream.
            ->timeout(120)
            ->post(
                "{$this->baseUrl}/{$this->model}:streamGenerateContent?alt=sse&key={$this->apiKey}",
                $this->basePayload($chat)
            );

        if ($response->failed()) {
            Log::error('Gemini API error (stream)', ['status' => $response->status()]);

            throw new RuntimeException('Gagal menghubungi Gemini API (stream): ' . $response->status());
        }

        $fullText = '';

        $this->consumeSse($response->getBody(), function (string $data) use (&$fullText, $onChunk) {
            $json = json_decode($data, true);
            $piece = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

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