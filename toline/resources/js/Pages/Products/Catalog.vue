<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    products: Object,
    search: String,
});

const searchTerm = ref(props.search);

const runSearch = debounce((value) => {
    router.get(route('catalog.index'), { search: value }, {
        preserveState: true,
        replace: true,
        only: ['products'],
    });
}, 350);

watch(searchTerm, (value) => runSearch(value));

const formatPrice = (value) =>
    'Rp ' + Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0 });
</script>

<template>
    <AppLayout title="Katalog Produk">
        <div class="mb-6">
            <input
                v-model="searchTerm"
                type="text"
                placeholder="Cari item, skun, atau top up di ToLine..."
                class="w-full max-w-xl rounded-full border border-slate-200 bg-white px-5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
            />
        </div>

        <h1 class="text-2xl font-bold text-slate-900">Katalog Produk</h1>
        <p class="mt-1 text-sm text-slate-500">Temukan item gaming terbaik dengan transaksi aman.</p>

        <div class="mt-6 grid grid-cols-4 gap-5">
            <Link
                v-for="product in products.data"
                :key="product.id"
                :href="route('products.show', product.id)"
                class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm transition hover:shadow-md"
            >
                <img :src="product.image_url ?? 'https://placehold.co/300x180'" class="h-32 w-full object-cover" />
                <div class="p-3">
                    <h3 class="truncate text-sm font-semibold text-slate-900">{{ product.name }}</h3>
                    <p class="text-xs text-slate-400">{{ product.category }}</p>
                    <p class="mt-2 text-sm font-bold text-toline-dark">{{ formatPrice(product.price) }}</p>
                </div>
            </Link>
        </div>

        <div v-if="products.data.length === 0" class="mt-10 text-center text-slate-400">
            Produk tidak ditemukan.
        </div>

        <div class="mt-8 flex justify-center">
            <button
                v-if="products.next_page_url"
                class="rounded-full bg-toline px-6 py-2 text-sm font-semibold text-white hover:bg-toline-dark"
                @click="router.get(products.next_page_url, {}, { preserveState: true, preserveScroll: true })"
            >
                Muat Lebih Banyak ⌄
            </button>
        </div>
    </AppLayout>
</template>