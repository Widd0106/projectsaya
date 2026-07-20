<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const showRoleModal = ref(true);
</script>

<template>
    <div class="min-h-screen bg-[#F5F4FC] font-sans">
        <header class="flex items-center justify-between px-10 py-5">
            <span class="text-xl font-bold text-toline-darker">ToLine</span>
            <div class="flex flex-1 justify-center px-10">
                <input
                    type="text"
                    placeholder="Cari item, game, atau top-up..."
                    class="w-full max-w-md rounded-full border border-slate-200 bg-white px-5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                />
            </div>
            <div class="flex items-center gap-3">
                <Link :href="route('login')" class="text-sm font-medium text-slate-600">Masuk</Link>
                <Link :href="route('register')" class="rounded-full bg-toline px-5 py-2 text-sm font-semibold text-white hover:bg-toline-dark">
                    Daftar
                </Link>
            </div>
        </header>

        <section class="px-10 py-10 text-center">
            <h1 class="mx-auto max-w-2xl text-4xl font-extrabold leading-tight text-slate-900">
                Tempat <span class="text-toline">Aman & Terpercaya</span> Untuk Belanja Kebutuhan Gaming
            </h1>
            <p class="mx-auto mt-4 max-w-xl text-slate-500">
                Ribuan transaksi sukses setiap harinya. Mulai dari akun, mata uang game, hingga top up instan.
            </p>
        </section>

        <div
            v-if="showRoleModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl">
                <div class="mb-6 flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Selamat Datang di ToLine</h2>
                        <p class="mt-1 text-sm text-slate-500">Pilih peran Anda untuk melanjutkan masuk ke platform kami.</p>
                    </div>
                    <button class="text-slate-400 hover:text-slate-600" @click="showRoleModal = false">✕</button>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <Link
                        :href="route('login')"
                        class="flex flex-col items-center gap-3 rounded-xl border border-slate-200 p-6 text-center hover:border-toline hover:bg-toline-light"
                    >
                        <span class="text-2xl">🛍️</span>
                        <span class="font-semibold text-slate-800">Masuk sebagai Pembeli</span>
                        <span class="text-xs text-slate-500">Cari item game & top up instan</span>
                    </Link>

                    <Link
                        :href="route('login')"
                        class="flex flex-col items-center gap-3 rounded-xl border border-slate-200 p-6 text-center hover:border-toline hover:bg-toline-light"
                    >
                        <span class="text-2xl">🏪</span>
                        <span class="font-semibold text-slate-800">Masuk sebagai Penjual</span>
                        <span class="text-xs text-slate-500">Kelola toko & tarik saldo kamu</span>
                    </Link>
                </div>

                <p class="mt-6 text-center text-sm text-slate-500">
                    Belum punya akun?
                    <Link :href="route('register')" class="font-semibold text-toline">Daftar Sekarang</Link>
                </p>
            </div>
        </div>
    </div>
</template>