<?php

namespace App\Services;

use App\Models\Product;
use App\Models\TransactionItem;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
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

        // Kalau pertanyaan menyebut nama produk tertentu, kirim detail LENGKAP produk itu
        // (termasuk deskripsi) supaya AI bisa jawab pertanyaan mendetail.
        $mentioned = $this->findMentionedProduct($question);
        if ($mentioned) {
            $context[] = "Detail LENGKAP produk yang sedang dibahas (gunakan ini untuk menjawab pertanyaan spesifik/mendetail):\n"
                . "- Nama: {$mentioned->name}\n"
                . "- Kategori/Game: {$mentioned->category}\n"
                . "- Tipe Item: {$mentioned->item_type}\n"
                . "- Harga: Rp" . number_format($mentioned->price, 0, ',', '.') . "\n"
                . "- Sisa stok: {$mentioned->stock} unit\n"
                . "- Terjual: {$mentioned->sold_count} unit\n"
                . "- Deskripsi dari penjual: " . ($mentioned->description ?: 'Penjual belum menuliskan deskripsi untuk produk ini.');
        }

        return implode("\n\n", array_filter($context));
    }

    protected function findMentionedProduct(string $question): ?Product
    {
        $questionLower = mb_strtolower($question);

        return Product::where('status', 'active')
            ->get(['id', 'name', 'category', 'item_type', 'price', 'stock', 'sold_count', 'description'])
            ->first(function ($p) use ($questionLower) {
                $nameLower = mb_strtolower($p->name);
                // cocokkan jika nama produk disebut di pertanyaan, atau sebagian besar kata nama produk muncul
                return str_contains($questionLower, $nameLower)
                    || str_contains($nameLower, $questionLower);
            });
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
        $lines[] = "- Rincian PERSIS per produk (nama, kategori, tipe, stok, terjual, harga, deskripsi):";
        foreach ($products->sortByDesc('sold_count') as $p) {
            $lines[] = "  * {$p->name} [{$p->category} / {$p->item_type}]: stok {$p->stock} unit, terjual {$p->sold_count} unit, "
                . "harga Rp" . number_format($p->price, 0, ',', '.')
                . ", deskripsi: " . ($p->description ?: '(belum diisi)');
        }

        return implode("\n", $lines);
    }

    protected function buildBuyerContext(): string
    {
        $products = Product::where('status', 'active')
            ->orderByDesc('sold_count')
            ->limit(15)
            ->get(['name', 'stock', 'sold_count', 'price', 'category', 'item_type', 'description']);

        if ($products->isEmpty()) {
            return "Belum ada produk aktif di marketplace ToLine saat ini.";
        }

        $lines = ["Data produk di marketplace ToLine saat ini (ini data REAL dari database, WAJIB dipakai apa adanya, JANGAN mengarang):"];
        foreach ($products as $p) {
            $lines[] = "- {$p->name} [{$p->category} / {$p->item_type}]: stok {$p->stock} unit, terjual {$p->sold_count} unit, "
                . "harga Rp" . number_format($p->price, 0, ',', '.')
                . ", deskripsi: " . ($p->description ?: '(belum diisi)');
        }

        return implode("\n", $lines);
    }

    public function ask(string $question, string $internalContext = '', array $history = []): array
    {
        $systemPrompt = "Kamu adalah ToLine Assistant, asisten pintar untuk marketplace item game bernama ToLine. "
            . "Jawab singkat, ramah, dan gunakan Bahasa Indonesia. "
            . "Kamu SUDAH memiliki akses ke data internal marketplace di bawah ini — data ini REAL dari database, bukan contoh. "
            . "SELALU gunakan angka dan detail PERSIS seperti di data, JANGAN PERNAH mengarang stok/harga/deskripsi. "
            . "Jika ditanya deskripsi/detail produk, jawab berdasarkan field deskripsi yang tersedia; jika belum diisi penjual, katakan jujur belum ada deskripsi. "
            . "Jika pengguna merujuk 'item tersebut'/'itu', lihat riwayat percakapan untuk tahu produk yang dimaksud. "
            . "JANGAN pernah bilang kamu tidak bisa mengakses data. "
            . "Jika pertanyaan di luar topik marketplace, jawab dengan pengetahuan umummu atau ambil data dari google.\n\n"
            . "Data internal marketplace saat ini:\n{$internalContext}";

        // Coba Gemini dengan retry sebelum menyerah
        try {
            return $this->withRetry(fn () => $this->askGemini($systemPrompt, $question, $history), attempts: 2);
        } catch (\Throwable $e) {
            Log::warning('Gemini gagal setelah retry, fallback ke Groq: ' . $e->getMessage());
        }

        // Coba Groq dengan retry sebelum menyerah
        try {
            return $this->withRetry(fn () => $this->askGroq($systemPrompt, $question, $history), attempts: 2);
        } catch (\Throwable $e) {
            Log::error('Groq juga gagal setelah retry: ' . $e->getMessage());
        }

        // Kedua provider AI down: jangan cuma minta maaf, kasih jawaban langsung dari database
        return $this->localFallbackAnswer($question, $internalContext);
    }

    /**
     * Jalankan sebuah closure dengan percobaan ulang otomatis (exponential backoff singkat)
     * sebelum benar-benar dianggap gagal. Ini mengurangi kemunculan "server sedang sibuk"
     * akibat kegagalan sesaat (timeout jaringan / rate limit sementara).
     */
    protected function withRetry(\Closure $callback, int $attempts = 2): array
    {
        $lastException = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $lastException = $e;

                // Kalau ini rate limit (429) atau server error (5xx), coba lagi setelah jeda singkat.
                // Kalau errornya soal auth/key salah, tidak ada gunanya diulang.
                $status = $e instanceof RequestException && $e->hasResponse()
                    ? $e->getResponse()->getStatusCode()
                    : null;

                if ($status === 401 || $status === 403) {
                    break; // API key salah, retry tidak akan membantu
                }

                if ($i < $attempts) {
                    usleep(400_000); // jeda 0.4 detik sebelum coba lagi
                }
            }
        }

        throw $lastException;
    }

    /**
     * Jawaban cadangan tanpa AI, dibuat langsung dari data internal, dipakai HANYA kalau
     * Gemini & Groq berdua benar-benar gagal (misal server AI down total).
     */
    protected function localFallbackAnswer(string $question, string $internalContext): array
    {
        $answer = "Maaf, asisten AI sedang mengalami gangguan koneksi ke server. "
            . "Tapi berikut data yang bisa saya berikan langsung dari sistem kami:\n\n"
            . ($internalContext ?: 'Tidak ada data yang relevan saat ini.')
            . "\n\nSilakan coba tanya lagi sebentar lagi untuk jawaban yang lebih natural.";

        return ['answer' => $answer, 'provider' => 'local_fallback'];
    }

    protected function askGemini(string $systemPrompt, string $question, array $history = []): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model');
        $timeout = config('services.gemini.timeout', 8);

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
                'connect_timeout' => 3,
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
            'timeout' => 10,
            'connect_timeout' => 3,
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