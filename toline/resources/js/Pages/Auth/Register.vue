<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { ShoppingBag, Store } from 'lucide-vue-next';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'buyer',
    store_name: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-[#F5F4FC] px-4 py-10 font-sans">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-toline text-lg font-bold text-white">T</div>
                <h1 class="text-2xl font-bold text-slate-900">Buat Akun ToLine</h1>
                <p class="mt-1 text-sm text-slate-500">Gabung dan mulai bertransaksi</p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nama Lengkap</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Konfirmasi</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                        />
                    </div>
                </div>
                <p v-if="form.errors.password" class="text-xs text-red-500">{{ form.errors.password }}</p>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Daftar sebagai</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="flex flex-col items-center gap-2 rounded-xl border p-4 text-center text-sm transition"
                            :class="form.role === 'buyer' ? 'border-toline bg-toline-light text-toline-dark' : 'border-slate-200 text-slate-600'"
                            @click="form.role = 'buyer'"
                        >
                            <ShoppingBag class="h-6 w-6" />
                            <span class="font-semibold">Pembeli</span>
                        </button>
                        <button
                            type="button"
                            class="flex flex-col items-center gap-2 rounded-xl border p-4 text-center text-sm transition"
                            :class="form.role === 'seller' ? 'border-toline bg-toline-light text-toline-dark' : 'border-slate-200 text-slate-600'"
                            @click="form.role = 'seller'"
                        >
                            <Store class="h-6 w-6" />
                            <span class="font-semibold">Penjual</span>
                        </button>
                    </div>
                </div>

                <div v-if="form.role === 'seller'">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nama Toko</label>
                    <input
                        v-model="form.store_name"
                        type="text"
                        class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                    />
                    <p v-if="form.errors.store_name" class="mt-1 text-xs text-red-500">{{ form.errors.store_name }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-full bg-toline py-2.5 text-sm font-semibold text-white hover:bg-toline-dark disabled:opacity-50"
                >
                    Daftar
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">
                Sudah punya akun?
                <Link :href="route('login')" class="font-semibold text-toline">Masuk</Link>
            </p>
        </div>
    </div>
</template>