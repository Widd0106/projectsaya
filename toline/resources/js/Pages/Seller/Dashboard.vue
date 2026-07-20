<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
    products: Array,
    balance: [Number, String],
    todayOrders: Number,
});

const user = usePage().props.auth.user;

const formatPrice = (value) =>
    'Rp ' + Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0 });

const removeProduct = (id) => {
    if (confirm('Hapus produk ini?')) {
        router.delete(route('seller.products.destroy', id));
    }
};
</script>

<template>
    <AppLayout title="Toko Saya">
        <div class="grid grid-cols-3 gap-5">
            <div class="col-span-2 flex items-center gap-4 rounded-xl bg-white p-5 shadow-sm">
                <div class="h-14 w-14 rounded-full bg-slate-200"></div>
                <div>
                    <h1 class="text-lg font-bold text-slate-900">{{ user.store_name ?? 'Toko Saya' }}</h1>
                    <p class="text-xs text-slate-500">Penyedia Top Up & In-Game Currency terpercaya sejak 2021.</p>
                </div>
            </div>

            <div class="rounded-xl bg-gradient-to-br from-toline to-toline-darker p-5 text-white shadow-sm">
                <p class="text-xs opacity-80">Saldo Penjual</p>
                <p class="text-2xl font-extrabold">{{ formatPrice(balance) }}</p>
                <p class="mt-2 text-xs opacity-80">Pesanan Hari ini: {{ todayOrders }}</p>
            </div>
        </div>

        <div class="mt-8 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">Toko Saya</h2>
            <Link
                :href="route('seller.products.create')"
                class="rounded-full bg-toline px-4 py-2 text-sm font-semibold text-white hover:bg-toline-dark"
            >
                + Tambah Item
            </Link>
        </div>

        <div class="mt-4 grid grid-cols-4 gap-5">
            <div
                v-for="product in products"
                :key="product.id"
                class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm"
            >
                <div class="relative">
                    <img :src="product.image_url ?? 'https://placehold.co/300x180'" class="h-28 w-full object-cover" />
                    <span
                        v-if="product.status !== 'active'"
                        class="absolute right-2 top-2 rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-semibold text-white"
                    >
                        {{ product.status === 'sold_out' ? 'Habis' : 'Nonaktif' }}
                    </span>
                </div>
                <div class="p-3">
                    <h3 class="truncate text-sm font-semibold">{{ product.name }}</h3>
                    <p class="text-xs text-slate-400">{{ product.category }} • Stok: {{ product.stock }}</p>
                    <p class="mt-1 text-sm font-bold text-toline-dark">{{ formatPrice(product.price) }}</p>
                    <button class="mt-2 text-xs font-medium text-red-500" @click="removeProduct(product.id)">Hapus</button>
                </div>
            </div>

            <!-- Kondisi card kosong: selalu tampil sebagai tombol tambah cepat -->
            <Link
                :href="route('seller.products.create')"
                class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 p-6 text-center text-slate-400 hover:border-toline hover:text-toline"
            >
                <span class="text-2xl">+</span>
                <span class="text-sm font-medium">Belum ada produk</span>
                <span class="text-xs">Tambah produk pertama Anda sekarang!</span>
            </Link>
        </div>

        <div v-if="products.length === 0" class="mt-4 text-center text-sm text-slate-400">
            Toko kamu masih kosong. Yuk tambahkan item pertamamu!
        </div>
    </AppLayout>
</template>