<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Search, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  characters: { type: Array, default: () => [] },
});

const page = usePage();
const searchQuery = ref('');
const characterToDelete = ref(null);

const filteredCharacters = computed(() => {
  if (!searchQuery.value.trim()) return props.characters;
  const q = searchQuery.value.toLowerCase();
  return props.characters.filter((c) => c.name.toLowerCase().includes(q));
});

function openChat(characterId) {
  router.visit(route('chat.show', characterId));
}

function askDelete(character) {
  characterToDelete.value = character;
}

function confirmDelete() {
  if (!characterToDelete.value) return;
  router.delete(route('characters.destroy', characterToDelete.value.id), {
    onFinish: () => {
      characterToDelete.value = null;
    },
  });
}
</script>

<template>
  <AppLayout>
    <div class="px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
      <div class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-md">
          <Search :size="18" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search characters"
            class="w-full rounded-full bg-white py-2.5 pl-11 pr-4 text-sm text-gray-900 placeholder-gray-500 outline-none"
          />
        </div>
        <div class="hidden text-sm font-medium text-gray-300 sm:block">
          {{ page.props.auth.user?.username }}
        </div>
      </div>

      <h1 class="mb-1 text-2xl font-bold text-accent sm:text-3xl">Your Character</h1>
      <p class="mb-6 text-sm text-gray-400 sm:mb-8">These are all the characters you created</p>

      <div v-if="filteredCharacters.length > 0" class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4">
        <button
          v-for="character in filteredCharacters"
          :key="character.id"
          @click="openChat(character.id)"
          class="group relative aspect-[4/5] overflow-hidden rounded-2xl border border-border-subtle bg-bg-card text-left transition hover:border-accent"
        >
          <img
            v-if="character.avatar_url"
            :src="character.avatar_url"
            :alt="character.name"
            class="absolute inset-0 h-full w-full object-cover"
          />
          <div v-else class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-bg-card to-bg-input text-4xl font-bold text-gray-600">
            {{ character.name.charAt(0) }}
          </div>

          <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>

          <div class="absolute bottom-0 left-0 right-0 p-3 sm:p-4">
            <h3 class="mb-1 text-sm font-bold text-white sm:text-lg">{{ character.name }}</h3>
            <p class="line-clamp-2 text-[11px] text-gray-300 sm:text-xs">{{ character.short_description }}</p>
          </div>

          <!-- Tombol Edit/Delete: selalu terlihat di mobile (tidak ada hover di touchscreen), muncul saat hover di desktop -->
          <div class="absolute right-2 top-2 flex gap-1.5 opacity-100 transition sm:right-3 sm:top-3 sm:gap-2 sm:opacity-0 sm:group-hover:opacity-100">
            <Link
              :href="route('characters.edit', character.id)"
              @click.stop
              class="rounded-full bg-black/60 px-2 py-1 text-[10px] text-white hover:bg-black/80 sm:px-3 sm:py-1 sm:text-xs"
            >
              Edit
            </Link>
            <button
              @click.stop="askDelete(character)"
              class="flex items-center justify-center rounded-full bg-black/60 p-1.5 text-white hover:bg-red-600"
            >
              <Trash2 :size="13" />
            </button>
          </div>
        </button>
      </div>

      <div v-else class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border-subtle px-4 py-16 text-center sm:py-24">
        <p class="mb-2 text-lg font-medium text-gray-300">Belum ada karakter</p>
        <p class="mb-6 text-sm text-gray-500">Mulai buat karakter AI pertamamu sekarang.</p>
        <Link
          :href="route('characters.create')"
          class="rounded-full bg-gradient-to-r from-accent-dark to-accent px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-accent/20"
        >
          + Create Character
        </Link>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="characterToDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4">
      <div class="w-full max-w-sm rounded-2xl border border-border-subtle bg-bg-panel p-6 text-center">
        <h3 class="mb-2 text-lg font-bold text-white">Hapus Karakter?</h3>
        <p class="mb-6 text-sm text-gray-400">
          Tindakan ini akan menghapus <span class="font-medium text-white">{{ characterToDelete.name }}</span>,
          seluruh riwayat chat, dan gambar avatarnya secara permanen. Tidak bisa dibatalkan.
        </p>
        <div class="flex gap-3">
          <button
            @click="characterToDelete = null"
            class="flex-1 rounded-full border border-border-subtle py-2.5 text-sm font-medium text-gray-300 transition hover:bg-bg-card"
          >
            Batal
          </button>
          <button
            @click="confirmDelete"
            class="flex-1 rounded-full bg-red-600 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700"
          >
            Hapus
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>