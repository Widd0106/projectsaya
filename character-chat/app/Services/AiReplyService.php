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
     * Coba provider utama (OpenRouter, dengan model fallback chain bawaan).
     * Kalau OpenRouter gagal total (kredit habis, semua model di chain gagal,
     * API key belum diset, dst), jatuhkan ke Gemini langsung sebagai provider
     * cadangan yang sepenuhnya independen (beda base URL, beda kredensial).
     * Kalau dua-duanya gagal, user tetap dapat balasan yang manusiawi,
     * bukan error 500 mentah.
     */
    public function generateReply(Chat $chat, string $userMessage): string
    {
        try {
            return $this->openRouter->generateReply($chat, $userMessage);
        } catch (Throwable $e) {
            Log::warning('OpenRouter gagal, fallback ke Gemini langsung.', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            return $this->gemini->generateReply($chat, $userMessage);
        } catch (Throwable $e) {
            Log::error('Semua provider AI gagal (OpenRouter & Gemini).', [
                'error' => $e->getMessage(),
            ]);
        }

        return "Duh, koneksi ke server AI-nya lagi bermasalah nih. Coba kirim pesannya lagi sebentar lagi ya.";
    }
}