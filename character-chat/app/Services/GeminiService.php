<?php

namespace App\Services;

use App\Models\Chat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiService
{
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

        $response = Http::timeout(30)->post(
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

    /**
     * Susun system prompt yang menggabungkan profil karakter
     * (nama, gender, deskripsi singkat & panjang) + contoh dialog,
     * agar gaya bicara AI konsisten dengan persona karakter.
     */
    protected function buildSystemPrompt($character): string
    {
        $prompt = "Kamu adalah karakter AI bernama \"{$character->name}\".\n\n";

        if ($character->gender) {
            $prompt .= "Gender: {$character->gender}\n";
        }

        $prompt .= "Deskripsi singkat: {$character->short_description}\n";

        if ($character->long_description) {
            $prompt .= "\nLatar belakang & kepribadian mendalam:\n{$character->long_description}\n";
        }

        $prompt .= "\nATURAN PENTING:\n";
        $prompt .= "- Selalu balas dalam karakter sebagai {$character->name}, jangan pernah keluar dari peran.\n";
        $prompt .= "- Jangan pernah menyebut dirimu sebagai AI, model bahasa, atau asisten.\n";
        $prompt .= "- Sesuaikan gaya bicara, nada, dan kosakata dengan kepribadian di atas.\n";
        $prompt .= "- Jaga agar respons terasa natural, hidup, dan konsisten dengan riwayat percakapan sebelumnya.\n";
        $prompt .= "- Balas seperti orang sungguhan mengetik chat, BUKAN seperti narasi novel atau naskah drama.\n";
        $prompt .= "- Hindari aksi/ekspresi berlebihan dalam tanda bintang (*tersenyum penuh misteri*, *menatap dalam-dalam*, dst). Maksimal satu aksi singkat per respons, dan hanya jika benar-benar perlu.\n";
        $prompt .= "- Hindari kalimat yang terlalu puitis, dramatis, atau berbunga-bunga. Bicara secukupnya, jangan bertele-tele.\n";
        $prompt .= "- Jangan ulangi nama lawan bicara atau nama diri sendiri di setiap kalimat, itu terdengar tidak natural.\n";
        $prompt .= "- Panjang respons singkat sampai sedang (1-4 kalimat), kecuali konteks memang butuh penjelasan panjang.\n";
        $prompt .= "- Sesekali boleh ada typo kecil, singkatan, atau gaya santai jika sesuai kepribadian karakter, supaya terasa seperti chat sungguhan bukan teks yang terlalu sempurna.\n";
        $prompt .= "- PENTING soal bahasa: selalu balas menggunakan bahasa yang sama dengan pesan terakhir dari lawan bicara, apapun bahasanya (Indonesia, Inggris, Jawa, Sunda, bahasa gaul/slang, campuran/code-switching, atau bahasa lainnya). Jangan terpaku ke satu bahasa tertentu, ikuti bahasa user secara natural seperti orang yang memang menguasai banyak bahasa.\n";

        if (! empty($character->example_dialogues)) {
            $prompt .= "\nContoh gaya bicara {$character->name} (ikuti nada dan pola bicaranya, bukan isinya secara harfiah):\n";

            foreach ($character->example_dialogues as $example) {
                $userLine = $example['user'] ?? '';
                $aiLine = $example['ai'] ?? '';

                if ($userLine === '' && $aiLine === '') {
                    continue;
                }

                $prompt .= "User: {$userLine}\n";
                $prompt .= "{$character->name}: {$aiLine}\n\n";
            }
        }

        return $prompt;
    }
}
