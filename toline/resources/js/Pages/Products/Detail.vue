<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Minus, Plus, ShieldCheck, Store } from 'lucide-vue-next';

const props = defineProps({
    product: Object,
    similarProducts: Array,
});

const quantity = ref(1);

const formatPrice = (value) =>
    'Rp ' + Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0 });

const totalPrice = computed(() => props.product.price * quantity.value);

const increment = () => {
    if (quantity.value < props.product.stock) quantity.value++;
};
const decrement = () => {
    if (quantity.value > 1) quantity.value--;
};

const buyNow = () => {
    router.post(route('checkout.prepare'), {
        items: [{ product_id: props.product.id, quantity: quantity.value }],
    });
};
</script>

<template>
    <AppLayout title="Detail Produk">
        <div class="grid grid-cols-2 gap-8">
            <div>
                <img
                    :src="product.image_url ?? 'https://placehold.co/500x300'"
                    class="h-72 w-full rounded-xl object-cover"
                />
            </div>

            <div>
                <p class="text-xs font-semibold uppercase text-toline">{{ product.category }} / {{ product.item_type }}</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ product.name }}</h1>

                <p class="mt-4 text-sm text-slate-500">Harga</p>
                <p class="text-3xl font-extrabold text-toline-dark">{{ formatPrice(product.price) }}</p>

                <div class="mt-6">
                    <p class="mb-2 text-sm font-medium text-slate-700">Jumlah</p>
                    <div class="flex items-center gap-4">
                        <button
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 text-slate-600 hover:bg-slate-50"
                            @click="decrement"
                        >
                            <Minus class="h-4 w-4" />
                        </button>
                        <span class="w-8 text-center font-semibold">{{ quantity }}</span>
                        <button
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 text-slate-600 hover:bg-slate-50"
                            @click="increment"
                        >
                            <Plus class="h-4 w-4" />
                        </button>
                        <span class="text-xs text-slate-400">Stok tersedia: {{ product.stock }}</span>
                    </div>
                </div>

                <button
                    class="mt-8 flex w-full items-center justify-center gap-2 rounded-full bg-toline py-3 text-sm font-semibold text-white hover:bg-toline-dark"
                    @click="buyNow"
                >
                    <ShieldCheck class="h-4 w-4" /> Beli Sekarang — {{ formatPrice(totalPrice) }}
                </button>

                <div class="mt-6 flex items-center gap-3 rounded-lg border border-slate-100 p-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200">
                        <Store class="h-4 w-4 text-slate-500" />
                    </div>
                    <span class="text-sm font-medium">{{ product.seller.store_name ?? product.seller.name }}</span>
                    <span class="ml-auto rounded-full border border-slate-200 px-3 py-1 text-xs">Lihat Toko</span>
                </div>
            </div>
        </div>

        <div class="mt-10">
            <h2 class="mb-3 text-lg font-bold text-slate-900">Deskripsi Lengkap</h2>
            <p class="whitespace-pre-line rounded-xl bg-white p-5 text-sm text-slate-600 shadow-sm">
                {{ product.description }}
            </p>
        </div>

        <div class="mt-10">
            <h2 class="mb-3 text-lg font-bold text-slate-900">Produk Serupa</h2>
            <div class="grid grid-cols-3 gap-5">
                <Link
                    v-for="item in similarProducts"
                    :key="item.id"
                    :href="route('products.show', item.id)"
                    class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm"
                >
                    <img :src="item.image_url ?? 'https://placehold.co/300x150'" class="h-28 w-full object-cover" />
                    <div class="p-3">
                        <p class="truncate text-sm font-semibold">{{ item.name }}</p>
                        <p class="text-sm font-bold text-toline-dark">{{ formatPrice(item.price) }}</p>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>