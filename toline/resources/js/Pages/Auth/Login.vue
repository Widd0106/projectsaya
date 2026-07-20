<script setup>
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-[#F5F4FC] px-4 font-sans">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-toline text-lg font-bold text-white">T</div>
                <h1 class="text-2xl font-bold text-slate-900">Masuk ke ToLine</h1>
                <p class="mt-1 text-sm text-slate-500">Belanja kebutuhan gaming dengan aman</p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
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

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-toline" />
                    Ingat saya
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-full bg-toline py-2.5 text-sm font-semibold text-white hover:bg-toline-dark disabled:opacity-50"
                >
                    Masuk
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">
                Belum punya akun?
                <Link :href="route('register')" class="font-semibold text-toline">Daftar Sekarang</Link>
            </p>
        </div>
    </div>
</template>