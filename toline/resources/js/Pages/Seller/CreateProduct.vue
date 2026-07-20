<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const form = useForm({
    name: '',
    category: '',
    item_type: 'top_up',
    price: 0,
    stock: 0,
    description: '',
    image: null,
});

const previewUrl = ref(null);

const onFileChange = (e) => {
    const file = e.target.files[0];
    form.image = file;
    previewUrl.value = file ? URL.createObjectURL(file) : null;
};

const submit = () => {
    form.post(route('seller.products.store'));
};
</script>

<template>
    <AppLayout title="Tambah Item Baru">
        <h1 class="text-2xl font-bold text-slate-900">Tambah Item Baru</h1>
        <p class="mt-1 text-sm text-slate-500">Lengkapi detail produk gaming yang ingin Anda jual di marketplace.</p>

        <form class="mt-6 grid grid-cols-2 gap-8" @submit.prevent="submit">
            <div>
                <p class="mb-2 text-sm font-medium text-slate-700">Foto Produk</p>
                <label
                    class="flex h-48 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 text-center text-slate-400 hover:border-toline"
                >
                    <img v-if="previewUrl" :src="previewUrl" class="h-full w-full rounded-xl object-cover" />
                    <template v-else>
                        <span class="text-2xl">⬆</span>
                        <span class="text-sm font-medium text-slate-600">Unggah Gambar</span>
                        <span class="text-xs">Tarik dan lepas file di sini, atau klik untuk memilih file dari komputer</span>
                        <span class="text-[10px] text-slate-400">MAKS. 2MB (JPG, PNG)</span>
                    </template>
                    <input type="file" accept="image/png,image/jpeg" class="hidden" @change="onFileChange" />
                </label>
                <p v-if="form.errors.image" class="mt-1 text-xs text-red-500">{{ form.errors.image }}</p>

                <div class="mt-5 rounded-xl bg-toline-light p-4 text-xs text-toline-darker">
                    <p class="mb-1 font-semibold">💡 Tips Jualan Cepat</p>
                    <ul class="list-disc space-y-1 pl-4">
                        <li>Gunakan judul yang spesifik (Level, Rank, Item)</li>
                        <li>Deskripsikan detail akun/item secara jelas</li>
                        <li>Harga yang kompetitif meningkatkan minat</li>
                    </ul>
                </div>
            </div>

            <div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nama Produk</label>
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="Contoh: Akun Valorant Immortal 3 Skins Glitchpop"
                        class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Kategori Game</label>
                        <select
                            v-model="form.category"
                            class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                        >
                            <option value="" disabled>Pilih Game</option>
                            <option value="Mobile Legends">Mobile Legends</option>
                            <option value="Valorant">Valorant</option>
                            <option value="Genshin Impact">Genshin Impact</option>
                            <option value="Free Fire">Free Fire</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Tipe Item</label>
                        <select
                            v-model="form.item_type"
                            class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                        >
                            <option value="akun_utama">Akun Utama</option>
                            <option value="top_up">Top Up</option>
                            <option value="jasa">Jasa</option>
                            <option value="item_koleksi">Item Koleksi</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Harga Jual</label>
                        <div class="flex items-center rounded-lg border border-slate-200 px-3">
                            <span class="text-sm text-slate-400">Rp</span>
                            <input
                                v-model.number="form.price"
                                type="number"
                                min="0"
                                class="w-full border-0 px-2 py-2.5 text-sm focus:outline-none focus:ring-0"
                            />
                        </div>
                        <p v-if="form.errors.price" class="mt-1 text-xs text-red-500">{{ form.errors.price }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Stok</label>
                        <input
                            v-model.number="form.stock"
                            type="number"
                            min="0"
                            class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                        />
                        <p v-if="form.errors.stock" class="mt-1 text-xs text-red-500">{{ form.errors.stock }}</p>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Deskripsi Produk</label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        placeholder="Jelaskan detail akun/item, spesifikasi, atau informasi penting lainnya..."
                        class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                    ></textarea>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-full px-5 py-2.5 text-sm font-medium text-slate-500">Batalkan</button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-full bg-toline px-6 py-2.5 text-sm font-semibold text-white hover:bg-toline-dark disabled:opacity-50"
                    >
                        💾 Simpan Produk
                    </button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>