<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const user = page.props.auth?.user;
</script>

<template>
    <div class="min-h-screen bg-[#F5F4FC] font-sans text-slate-800">
        <div class="mx-auto flex max-w-[1400px]">
            <aside class="sticky top-0 flex h-screen w-64 flex-col border-r border-slate-200 bg-white px-4 py-6">
                <div class="mb-8 flex items-center gap-2 px-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-toline text-sm font-bold text-white">T</div>
                    <span class="text-lg font-bold text-toline-darker">ToLine</span>
                </div>

                <nav class="flex flex-1 flex-col gap-1">
                    <Link
                        :href="route('catalog.index')"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium"
                        :class="route().current('catalog.index') ? 'bg-toline-light text-toline-dark' : 'text-slate-600 hover:bg-slate-50'"
                    >
                        <span>🏠</span> Home (Katalog)
                    </Link>

                    <Link
                        :href="route('ai-chat.index')"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium"
                        :class="route().current('ai-chat.index') ? 'bg-toline-light text-toline-dark' : 'text-slate-600 hover:bg-slate-50'"
                    >
                        <span>💬</span> Tanya AI
                    </Link>

                    <Link
                        v-if="user?.role !== 'seller'"
                        :href="route('transactions.index')"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                        >
                        <span>🧾</span> Pesanan Saya
                    </Link>

                    <div v-if="user?.role === 'seller'" class="mt-2 border-t border-slate-100 pt-2">
                        <Link
                            :href="route('seller.dashboard')"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium"
                            :class="route().current('seller.dashboard') ? 'bg-toline-light text-toline-dark' : 'text-slate-600 hover:bg-slate-50'"
                        >
                            <span>📊</span> Dashboard
                        </Link>
                        <Link
                            :href="route('seller.products.create')"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                        >
                            <span>➕</span> Tambah Item
                        </Link>
                    </div>

                    <div class="mt-6">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Riwayat Chat</p>
                        <slot name="chat-history" />
                    </div>
                </nav>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="mt-4 flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-red-500 hover:bg-red-50"
                >
                    <span>↩</span> Keluar
                </Link>
            </aside>

            <main class="flex-1 px-8 py-6">
                <slot />
            </main>
        </div>
    </div>
</template>