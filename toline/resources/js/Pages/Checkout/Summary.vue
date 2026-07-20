<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    items: Array,
    subtotal: Number,
    serviceFee: Number,
    adminFee: Number,
    total: Number,
});

const formatPrice = (value) =>
    'Rp ' + Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0 });

const pay = () => {
    router.post(route('transactions.store'), {
        items: props.items.map((item) => ({
            product_id: item.product.id,
            quantity: item.quantity,
        })),
    });
};
</script>

<template>
    <AppLayout title="Ringkasan Pesanan">
        <h1 class="mb-6 text-2xl font-bold text-slate-900">Ringkasan Pesanan</h1>
        <p class="mb-6 -mt-4 text-sm text-slate-500">Tinjau kembali detail pesanan Anda sebelum melakukan pembayaran.</p>

        <div class="grid grid-cols-3 gap-6 rounded-xl border-2 border-toline p-6">
            <div class="col-span-2">
                <h2 class="mb-3 font-semibold text-slate-800">Item Pesanan</h2>
                <div v-for="(item, idx) in items" :key="idx" class="mb-3 flex items-center gap-3 border-b border-slate-100 pb-3">
                    <img :src="item.product.image_url ?? 'https://placehold.co/60'" class="h-12 w-12 rounded-lg object-cover" />
                    <div class="flex-1">
                        <p class="text-sm font-semibold">{{ item.product.name }}</p>
                        <p class="text-xs text-slate-400">Rp {{ Number(item.product.price).toLocaleString('id-ID') }} • Qty {{ item.quantity }}</p>
                    </div>
                    <p class="text-sm font-bold text-toline-dark">{{ formatPrice(item.lineTotal) }}</p>
                </div>
            </div>

            <div>
                <h2 class="mb-3 font-semibold text-slate-800">Total Biaya</h2>
                <div class="space-y-2 text-sm text-slate-600">
                    <div class="flex justify-between"><span>Subtotal</span><span>{{ formatPrice(subtotal) }}</span></div>
                    <div class="flex justify-between"><span>Biaya Layanan</span><span>{{ formatPrice(serviceFee) }}</span></div>
                    <div class="flex justify-between"><span>Biaya Admin</span><span>{{ formatPrice(adminFee) }}</span></div>
                </div>
                <div class="mt-3 flex justify-between border-t border-slate-200 pt-3 font-bold text-slate-900">
                    <span>Total Bayar</span><span>{{ formatPrice(total) }}</span>
                </div>

                <button
                    class="mt-4 w-full rounded-full bg-toline py-2.5 text-sm font-semibold text-white hover:bg-toline-dark"
                    @click="pay"
                >
                    Bayar Sekarang
                </button>
            </div>
        </div>
    </AppLayout>
</template>