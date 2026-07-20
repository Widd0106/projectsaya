<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    transactions: Array,
});

const formatPrice = (value) =>
    'Rp ' + Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0 });

const statusLabel = {
    pending: 'Menunggu Pembayaran',
    paid: 'Dibayar',
    completed: 'Selesai',
    failed: 'Gagal',
};
</script>

<template>
    <AppLayout title="Pesanan Saya">
        <h1 class="mb-6 text-2xl font-bold text-slate-900">Pesanan Saya</h1>

        <div v-if="transactions.length === 0" class="rounded-xl bg-white p-8 text-center text-slate-400 shadow-sm">
            Belum ada pesanan. Yuk mulai belanja di katalog!
        </div>

        <div v-for="trx in transactions" :key="trx.id" class="mb-4 rounded-xl bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <p class="text-sm font-semibold text-slate-800">{{ trx.invoice_number }}</p>
                    <p class="text-xs text-slate-400">{{ new Date(trx.created_at).toLocaleString('id-ID') }}</p>
                </div>
                <span class="rounded-full bg-toline-light px-3 py-1 text-xs font-medium text-toline-dark">
                    {{ statusLabel[trx.status] ?? trx.status }}
                </span>
            </div>

            <div v-for="item in trx.items" :key="item.id" class="flex items-center justify-between py-2 text-sm">
                <span>{{ item.product.name }} x{{ item.quantity }}</span>
                <span class="font-medium">{{ formatPrice(item.price * item.quantity) }}</span>
            </div>

            <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                <span class="text-sm font-semibold">Total</span>
                <span class="font-bold text-toline-dark">{{ formatPrice(trx.total) }}</span>
            </div>
        </div>
    </AppLayout>
</template>