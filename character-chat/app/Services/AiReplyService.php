<?php

namespace App\Services;

use App\Models\Chat;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiReplyService
{
    public function __construct(
        protected OpenRouterService $openRouter,
        protected GeminiService $gemini,
    ) {
    }

    /**
     * Non-streaming: coba OpenRouter dulu, fallback ke Gemini, lalu pesan
     * ramah kalau dua-duanya gagal total.
     */
    public function generateReply(Chat $chat): string
    {
        try {
            return $this->openRouter->generateReply($chat);
        } catch (Throwable $e) {
            Log::warning('OpenRouter gagal, fallback ke Gemini langsung.', ['error' => $e->getMessage()]);
        }

        try {
            return $this->gemini->generateReply($chat);
        } catch (Throwable $e) {
            Log::error('Semua provider AI gagal (OpenRouter & Gemini).', ['error' => $e->getMessage()]);
        }

        return "Duh, koneksi ke server AI-nya lagi bermasalah nih. Coba kirim pesannya lagi sebentar lagi ya.";
    }

    /**
     * Streaming: sama seperti generateReply, tapi $onChunk dipanggil tiap
     * ada potongan teks baru supaya bisa langsung dikirim ke browser (SSE).
     *
     * Kalau OpenRouter sempat mengirim sebagian teks lalu putus di
     * tengah jalan, kita TIDAK pindah ke Gemini (supaya user tidak lihat
     * dua jawaban tercampur) — exception dilempar lagi ke pemanggil,
     * yang bertanggung jawab menyimpan teks parsial yang sudah terkirim.
     */
    public function streamReply(Chat $chat, callable $onChunk): string
    {
        $chunkEmitted = false;
        $trackedChunk = function (string $piece) use (&$chunkEmitted, $onChunk) {
            $chunkEmitted = true;
            $onChunk($piece);
        };

        try {
            return $this->openRouter->streamReply($chat, $trackedChunk);
        } catch (Throwable $e) {
            Log::warning('OpenRouter streaming gagal.', ['error' => $e->getMessage(), 'partial_sent' => $chunkEmitted]);

            if ($chunkEmitted) {
                throw $e;
            }
        }

        try {
            return $this->gemini->streamReply($chat, $onChunk);
        } catch (Throwable $e) {
            Log::error('Semua provider AI (stream) gagal.', ['error' => $e->getMessage()]);

            throw $e;
        }
    }
}