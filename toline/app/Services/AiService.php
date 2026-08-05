<?php

namespace App\Services;

use App\Models\Product;
use App\Models\TransactionItem;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class AiService
{
    protected Client $http;

    public function __construct()
    {
        $this->http = new Client();
    }

    /* =========================================================
     |  DAFTAR "FUNGSI" YANG BOLEH DIPANGGIL AI (Function Calling)
     |  Di sinilah kamu cukup daftar fungsinya sekali, tidak perlu
     |  menebak konteks per skenario lagi seperti sebelumnya.
     * ========================================================= */

    protected function availableTools(): array
    {
        return [
            [
                'name' => 'get_product_detail',
                'description' => 'Ambil detail LENGKAP satu produk (harga, stok, deskripsi, kategori, jumlah terjual) berdasarkan nama produk. Gunakan ini kalau user bertanya spesifik tentang satu produk.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_name' => ['type' => 'string', 'description' => 'Nama produk atau sebagian nama produk yang dicari'],
                    ],
                    'required' => ['product_name'],
                ],
            ],
            [
                'name' => 'search_products',
                'description' => 'Cari daftar produk berdasarkan kata kunci nama, kategori, atau game.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'description' => 'Kata kunci pencarian'],
                    ],
                    'required' => ['keyword'],
                ],
            ],
            [
                'name' => 'list_top_products',
                'description' => 'Ambil daftar produk terlaris/terpopuler di seluruh marketplace ToLine.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Jumlah produk yang ditampilkan, default 5'],
                    ],
                ],
            ],
            [
                'name' => 'get_seller_dashboard',
                'description' => 'Ambil ringkasan performa toko milik penjual yang SEDANG LOGIN: total produk, total terjual, penjualan bulan ini, saldo, dan rincian tiap produk. Gunakan ini kalau penjual bertanya soal performa/penjualan/stok tokonya sendiri.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
        ];
    }

    protected function executeTool(string $name, array $args, ?User $user): array
    {
        return match ($name) {
            'get_product_detail' => $this->toolGetProductDetail($args['product_name'] ?? ''),
            'search_products' => $this->toolSearchProducts($args['keyword'] ?? ''),
            'list_top_products' => $this->toolListTopProducts((int) ($args['limit'] ?? 5)),
            'get_seller_dashboard' => $this->toolGetSellerDashboard($user),
            default => ['error' => "Fungsi '{$name}' tidak dikenal."],
        };
    }

    protected function toolGetProductDetail(string $productName): array
    {
        $product = Product::where('status', 'active')
            ->where('name', 'like', "%{$productName}%")
            ->first();

        if (!$product) {
            return ['found' => false, 'message' => "Produk dengan nama mengandung '{$productName}' tidak ditemukan."];
        }

        return [
            'found' => true,
            'name' => $product->name,
            'category' => $product->category,
            'item_type' => $product->item_type,
            'price' => (int) $product->price,
            'stock' => $product->stock,
            'sold_count' => $product->sold_count,
            'description' => $product->description ?: 'Belum ada deskripsi dari penjual.',
        ];
    }

    protected function toolSearchProducts(string $keyword): array
    {
        $products = Product::where('status', 'active')
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('category', 'like', "%{$keyword}%");
            })
            ->limit(10)
            ->get(['name', 'category', 'price', 'stock', 'sold_count']);

        return ['results' => $products->toArray()];
    }

    protected function toolListTopProducts(int $limit = 5): array
    {
        $products = Product::where('status', 'active')
            ->orderByDesc('sold_count')
            ->limit(min($limit, 15))
            ->get(['name', 'category', 'price', 'sold_count', 'stock']);

        return ['results' => $products->toArray()];
    }

    protected function toolGetSellerDashboard(?User $user): array
    {
        if (!$user || !$user->isSeller()) {
            return ['error' => 'Pengguna ini bukan penjual atau belum login.'];
        }

        $products = Product::where('user_id', $user->id)->get();

        if ($products->isEmpty()) {
            return [
                'has_products' => false,
                'message' => 'Penjual belum menambahkan produk apa pun ke tokonya.',
            ];
        }

        $thisMonthSold = TransactionItem::whereHas('product', fn ($q) => $q->where('user_id', $user->id))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('quantity');

        return [
            'has_products' => true,
            'store_name' => $user->store_name,
            'total_products' => $products->count(),
            'total_sold_all_time' => $products->sum('sold_count'),
            'total_sold_this_month' => $thisMonthSold,
            'total_stock' => $products->sum('stock'),
            'balance' => (int) $user->balance,
            'products' => $products->map(fn ($p) => [
                'name' => $p->name,
                'stock' => $p->stock,
                'sold_count' => $p->sold_count,
                'price' => (int) $p->price,
                'description' => $p->description ?: '(belum diisi)',
            ])->toArray(),
        ];
    }

    /* =========================================================
     |  ENTRY POINT
     * ========================================================= */

    public function ask(string $question, ?User $user, array $history = []): array
    {
        $basePrompt = "Kamu adalah ToLine Assistant, asisten pintar untuk marketplace item game bernama ToLine. "
            . "Jawab singkat, ramah, dan gunakan Bahasa Indonesia. "
            . "Kamu punya akses ke FUNGSI (tools) untuk mengambil data REAL dari database ToLine kapan pun kamu butuh — "
            . "gunakan fungsi yang sesuai SEBELUM menjawab pertanyaan yang berkaitan dengan produk, stok, harga, deskripsi, atau performa toko. "
            . "JANGAN PERNAH mengarang data, selalu panggil fungsi dulu untuk memastikan datanya benar. "
            . "Jika pengguna merujuk 'item tersebut'/'itu', lihat riwayat percakapan untuk tahu produk yang dimaksud. "
            . "Jika pertanyaan di luar topik marketplace, jawab dengan pengetahuan umummu.";

        try {
            return $this->withRetry(fn () => $this->askGemini($basePrompt, $question, $history, $user), attempts: 2);
        } catch (\Throwable $e) {
            Log::warning('Gemini gagal setelah retry, fallback ke Groq: ' . $e->getMessage());
        }

        // Groq (di sini) belum mendukung function calling multi-round yang sama,
        // jadi sebagai fallback kita tetap suapkan ringkasan data secara statis.
        $staticContext = $this->buildStaticContext($question, $user);
        $fallbackPrompt = $basePrompt . "\n\nData internal marketplace saat ini:\n{$staticContext}";

        try {
            return $this->withRetry(fn () => $this->askGroq($fallbackPrompt, $question, $history), attempts: 2);
        } catch (\Throwable $e) {
            Log::error('Groq juga gagal setelah retry: ' . $e->getMessage());
        }

        return $this->localFallbackAnswer($staticContext);
    }

    /* =========================================================
     |  GEMINI — dengan Function Calling (multi-round)
     * ========================================================= */

    protected function askGemini(string $systemPrompt, string $question, array $history, ?User $user): array
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

        $maxRounds = 4; // batas aman supaya tidak infinite loop kalau AI terus minta panggil fungsi

        for ($round = 0; $round < $maxRounds; $round++) {
            $response = $this->http->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'timeout' => $timeout,
                    'connect_timeout' => 3,
                    'json' => [
                        'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                        'tools' => [['function_declarations' => $this->availableTools()]],
                        'contents' => $contents,
                    ],
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $parts = $data['candidates'][0]['content']['parts'] ?? [];

            $functionCalls = array_filter($parts, fn ($p) => isset($p['functionCall']));

            if (empty($functionCalls)) {
                $answer = collect($parts)->pluck('text')->filter()->implode("\n");
                if (!$answer) {
                    throw new \RuntimeException('Respons Gemini kosong.');
                }
                return ['answer' => $answer, 'provider' => 'gemini'];
            }

            // AI minta panggil satu/lebih fungsi -> jalankan di Laravel, lalu kirim balik hasilnya
            $contents[] = ['role' => 'model', 'parts' => $parts];

            $functionResponseParts = [];
            foreach ($functionCalls as $call) {
                $fnName = $call['functionCall']['name'];
                $fnArgs = $call['functionCall']['args'] ?? [];
                $result = $this->executeTool($fnName, $fnArgs, $user);

                $functionResponseParts[] = [
                    'functionResponse' => [
                        'name' => $fnName,
                        'response' => $result,
                    ],
                ];
            }

            $contents[] = ['role' => 'user', 'parts' => $functionResponseParts];
        }

        throw new \RuntimeException('Gemini tidak memberi jawaban akhir setelah beberapa kali pemanggilan fungsi.');
    }

    /* =========================================================
     |  GROQ — fallback tanpa function calling (pakai context statis)
     * ========================================================= */

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

    /* =========================================================
     |  RETRY & FALLBACK LOKAL (mengurangi "server sedang sibuk")
     * ========================================================= */

    protected function withRetry(\Closure $callback, int $attempts = 2): array
    {
        $lastException = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $lastException = $e;

                $status = $e instanceof RequestException && $e->hasResponse()
                    ? $e->getResponse()->getStatusCode()
                    : null;

                if ($status === 401 || $status === 403) {
                    break; // API key salah, retry tidak akan membantu
                }

                if ($i < $attempts) {
                    usleep(400_000);
                }
            }
        }

        throw $lastException;
    }

    protected function localFallbackAnswer(string $staticContext): array
    {
        $answer = "Maaf, asisten AI sedang mengalami gangguan koneksi ke server. "
            . "Tapi berikut data yang bisa saya berikan langsung dari sistem kami:\n\n"
            . ($staticContext ?: 'Tidak ada data yang relevan saat ini.')
            . "\n\nSilakan coba tanya lagi sebentar lagi untuk jawaban yang lebih natural.";

        return ['answer' => $answer, 'provider' => 'local_fallback'];
    }

    /* =========================================================
     |  Context statis — HANYA dipakai sebagai fallback Groq
     * ========================================================= */

    protected function buildStaticContext(string $question, ?User $user): string
    {
        if (!$user) {
            return '';
        }

        return $user->isSeller() ? $this->buildSellerContext($user) : $this->buildBuyerContext();
    }

    protected function buildSellerContext(User $user): string
    {
        $products = Product::where('user_id', $user->id)->get();

        if ($products->isEmpty()) {
            return "Penjual ini belum memiliki produk apa pun di tokonya (0 produk).";
        }

        $lines = ["Data toko '{$user->store_name}':"];
        foreach ($products->sortByDesc('sold_count') as $p) {
            $lines[] = "- {$p->name}: stok {$p->stock}, terjual {$p->sold_count}, harga Rp" . number_format($p->price, 0, ',', '.');
        }

        return implode("\n", $lines);
    }

    protected function buildBuyerContext(): string
    {
        $products = Product::where('status', 'active')->orderByDesc('sold_count')->limit(10)->get();

        if ($products->isEmpty()) {
            return "Belum ada produk aktif di marketplace.";
        }

        $lines = ["Produk di marketplace ToLine:"];
        foreach ($products as $p) {
            $lines[] = "- {$p->name}: stok {$p->stock}, terjual {$p->sold_count}, harga Rp" . number_format($p->price, 0, ',', '.');
        }

        return implode("\n", $lines);
    }
}