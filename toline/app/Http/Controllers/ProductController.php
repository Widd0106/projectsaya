<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');

        $products = Product::query()
            ->where('status', 'active')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->with('seller:id,name,store_name')
            ->latest()
            ->paginate(8)
            ->withQueryString();

        return Inertia::render('Products/Catalog', [
            'products' => $products,
            'search' => $search ?? '',
        ]);
    }

    public function show(Product $product): Response
    {
        $product->load('seller:id,name,store_name,store_avatar');

        $similarProducts = Product::query()
            ->where('category', $product->category)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->limit(3)
            ->get();

        return Inertia::render('Products/Detail', [
            'product' => $product,
            'similarProducts' => $similarProducts,
        ]);
    }

    public function myProducts(Request $request): Response
    {
        $products = $request->user()
            ->products()
            ->latest()
            ->get();

        $todayOrders = \App\Models\TransactionItem::query()
            ->whereHas('product', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereDate('created_at', today())
            ->count();

        return Inertia::render('Seller/Dashboard', [
            'products' => $products,
            'balance' => $request->user()->balance,
            'todayOrders' => $todayOrders,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Seller/CreateProduct');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'item_type' => 'required|in:akun_utama,top_up,jasa,item_koleksi',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $path = $request->file('image')->store('products', 'public');

        $request->user()->products()->create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'item_type' => $validated['item_type'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'description' => $validated['description'] ?? null,
            'image_path' => $path,
            'status' => 'active',
        ]);

        return redirect()->route('seller.dashboard')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function destroy(Product $product)
    {
        abort_unless($product->user_id === auth()->id(), 403);

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return redirect()->route('seller.dashboard')
            ->with('success', 'Produk berhasil dihapus.');
    }
}