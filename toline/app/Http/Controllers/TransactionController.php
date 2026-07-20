<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $transactions = $request->user()
            ->transactions()
            ->with('items.product')
            ->latest()
            ->get();

        return Inertia::render('Checkout/Index', [
            'transactions' => $transactions,
        ]);
    }

    public function prepare(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        session(['checkout_items' => $validated['items']]);

        return redirect()->route('checkout.summary');
    }

    public function summary(Request $request): Response
    {
        $rawItems = session('checkout_items');
        abort_if(!$rawItems, 404, 'Tidak ada item untuk di-checkout.');

        $items = collect($rawItems)->map(function ($item) {
            $product = Product::findOrFail($item['product_id']);
            return [
                'product' => $product,
                'quantity' => $item['quantity'],
                'lineTotal' => $product->price * $item['quantity'],
            ];
        });

        $subtotal = $items->sum('lineTotal');
        $serviceFee = 1500;
        $adminFee = 2000;

        return Inertia::render('Checkout/Summary', [
            'items' => $items,
            'subtotal' => $subtotal,
            'serviceFee' => $serviceFee,
            'adminFee' => $adminFee,
            'total' => $subtotal + $serviceFee + $adminFee,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $transaction = DB::transaction(function () use ($validated, $request) {
            $items = collect($validated['items'])->map(function ($item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                abort_if($product->stock < $item['quantity'], 422, 'Stok tidak mencukupi.');
                return ['product' => $product, 'quantity' => $item['quantity']];
            });

            $subtotal = $items->sum(fn ($i) => $i['product']->price * $i['quantity']);
            $serviceFee = 1500;
            $adminFee = 2000;

            $transaction = Transaction::create([
                'invoice_number' => 'TL-' . strtoupper(Str::random(10)),
                'buyer_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'service_fee' => $serviceFee,
                'admin_fee' => $adminFee,
                'total' => $subtotal + $serviceFee + $adminFee,
                'status' => 'paid',
            ]);

            foreach ($items as $item) {
                $transaction->items()->create([
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'price' => $item['product']->price,
                ]);

                $item['product']->decrement('stock', $item['quantity']);
                $item['product']->increment('sold_count', $item['quantity']);
            }

            session()->forget('checkout_items');

            return $transaction;
        });

        return redirect()->route('transactions.show', $transaction)
            ->with('success', 'Pembayaran berhasil.');
    }

    public function show(Transaction $transaction): Response
    {
        abort_unless($transaction->buyer_id === auth()->id(), 403);
        $transaction->load('items.product');

        return Inertia::render('Checkout/Success', [
            'transaction' => $transaction,
        ]);
    }
}