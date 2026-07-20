<?php

namespace App\Services;

use App\Models\Product;
use App\Models\TransactionItem;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AiService
{
    protected Client $http;

    public function __construct()
    {
        $this->http = new Client();
    }

    public function buildInternalContext(string $question, ?User $user): string
    {
        if (!$user) {
            return '';
        }

        $context = [];
        $context[] = $user->isSeller()
            ? $this->buildSellerContext($user)
            : $this->buildBuyerContext();

        return implode("\n\n", array_filter($context));
    }

    protected function buildSellerContext(User $user): string
    {
        $products = Product::where('user_id', $user->id)->get();

        if ($products->isEmpty()) {
            return "PENTING: Penjual ini belum memiliki produk apa pun di tokonya (0 produk). "
                . "Jika ditanya soal performa/penjualan/stok, jawab bahwa belum ada data karena belum ada item yang ditambahkan, "
                . "dan sarankan untuk menambah item pertama lewat menu 'Tambah Item'.";
        }

        $totalStock = $products->sum('stock');
        $totalSold = $products->sum('sold_count');

        $thisMonthSold = TransactionItem::whereHas('product', fn ($q) => $q->where('user_id', $user->id))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('quantity');

        $lines = [];
        $lines[] = "Data toko '{$user->store_name}' milik penjual ini (ini data REAL dari database, WAJIB dipakai apa adanya):";
        $lines[] = "- Total produk aktif: {$products->count()}";
        $lines[] = "- Total unit terjual sepanjang waktu: {$totalSold}";
        $lines[] = "- Total unit terjual bulan ini: {$thisMonthSold}";
        $lines[] = "- Sisa stok keseluruhan: {$totalStock}";
        $lines[] = "- Saldo penjual saat ini: Rp" . number_format($user->balance, 0, ',', '.');
        $lines[] = "- Rincian PERSIS per produk (nama: sisa stok saat ini, jumlah terjual, harga):";
        foreach ($products->sortByDesc('sold_count') as $p) {
            $lines[] = "  * {$p->name}: stok {$p->stock} unit, terjual {$p->sold_count} unit, harga Rp" . number_format($p->price, 0, ',', '.');
        }

        return implode("\n", $lines);
    }

    protected function buildBuyerContext(): string
    {
        $products = Product::where('status', 'active')
            ->orderByDesc('sold_count')
            ->limit(15)
            ->get(['name', 'stock', 'sold_count', 'price', 'category']);

        if ($products->isEmpty()) {
            return "Belum ada produk aktif di marketplace ToLine saat ini.";
        }

        $lines = ["Data produk di marketplace ToLine saat ini (ini data REAL dari database, WAJIB dipakai apa adanya, JANGAN mengarang angka):"];
        foreach ($products as $p) {
            $lines[] = "- {$p->name} ({$p->category}): stok {$p->stock} unit, terjual {$p->sold_count} unit, harga Rp" . number_format($p->price, 0, ',', '.');
        }

        return implode("\n", $lines);
    }

    public function ask(string $question, string $internalContext = '', array $history = []): array
    {
        $systemPrompt = "Kamu adalah ToLine Assistant, asisten pintar untuk marketplace item game bernama ToLine. "
            . "Jawab singkat, ramah, dan gunakan Bahasa Indonesia. "
            . "Kamu SUDAH memiliki akses ke data internal marketplace di bawah ini — data ini adalah data REAL dari database, bukan contoh. "
            . "SELALU gunakan angka PERSIS seperti yang tertulis di data, JANGAN PERNAH mengarang atau menebak angka stok/harga/jumlah terjual. "
            . "Jika pengguna merujuk ke 'item tersebut' atau 'itu', lihat riwayat percakapan sebelumnya untuk tahu produk mana yang dimaksud. "
            . "JANGAN pernah bilang kamu tidak bisa mengakses data atau menyuruh pengguna membuka dashboard sendiri. "
            . "Jika pertanyaan di luar topik marketplace, jawab dengan pengetahuan umummu.\n\n"
            . "Data internal marketplace saat ini:\n{$internalContext}";

        try {
            return $this->askGemini($systemPrompt, $question, $history);
        } catch (\Throwable $e) {
            Log::warning('Gemini gagal, fallback ke Groq: ' . $e->getMessage());

            try {
                return $this->askGroq($systemPrompt, $question, $history);
            } catch (\Throwable $e2) {
                Log::error('Groq juga gagal: ' . $e2->getMessage());
                return [
                    'answer' => 'Maaf, asisten AI sedang tidak dapat diakses. Silakan coba lagi sebentar lagi.',
                    'provider' => 'none',
                ];
            }
        }
    }

    protected function askGemini(string $systemPrompt, string $question, array $history = []): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model');
        $timeout = config('services.gemini.timeout', 5);

        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $question]]];

        $response = $this->http->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'timeout' => $timeout,
                'json' => [
                    'system_instruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents' => $contents,
                ],
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);
        $answer = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$answer) {
            throw new \RuntimeException('Respons Gemini kosong.');
        }

        return ['answer' => $answer, 'provider' => 'gemini'];
    }

    protected function askGroq(string $systemPrompt, string $question, array $history = []): array
    {
        $apiKey = config('services.groq.key');
        $model = config('services.groq.model');

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $msg['content'],
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $question];

        $response = $this->http->post('https://api.groq.com/openai/v1/chat/completions', [
            'timeout' => 8,
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'messages' => $messages,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $answer = $data['choices'][0]['message']['content'] ?? null;

        if (!$answer) {
            throw new \RuntimeException('Respons Groq kosong.');
        }

        return ['answer' => $answer, 'provider' => 'groq'];
    }
}