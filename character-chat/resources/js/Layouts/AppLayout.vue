<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { Plus, BookOpen, Search, User as UserIcon, LogOut, Menu, X } from 'lucide-vue-next';

const page = usePage();
const sidebarSearch = ref('');
const isMobileMenuOpen = ref(false);

const recentChats = computed(() => page.props.sidebarRecentChats || []);
const username = computed(() => page.props.auth?.user?.username || '');

const filteredChats = computed(() => {
  if (!sidebarSearch.value.trim()) return recentChats.value;
  const q = sidebarSearch.value.toLowerCase();
  return recentChats.value.filter((c) => c.characterName.toLowerCase().includes(q));
});

function goToChat(characterId) {
  isMobileMenuOpen.value = false;
  router.visit(route('chat.show', characterId));
}

function logout() {
  router.post(route('logout'));
}
</script>

<template>
  <div class="flex h-screen w-full overflow-hidden bg-bg text-white">
    <!-- Backdrop overlay saat sidebar mobile terbuka -->
    <div
      v-if="isMobileMenuOpen"
      @click="isMobileMenuOpen = false"
      class="fixed inset-0 z-40 bg-black/60 lg:hidden"
    ></div>

    <!-- Sidebar: fixed overlay di mobile (slide-in), statis di desktop (lg+) -->
    <aside
      class="fixed inset-y-0 left-0 z-50 flex w-[280px] flex-shrink-0 flex-col border-r border-border-subtle bg-bg-panel px-6 py-6 transition-transform duration-200 lg:relative lg:translate-x-0"
      :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="mb-8 flex items-center justify-between">
        <Link :href="route('dashboard')" class="text-xl font-bold text-accent" @click="isMobileMenuOpen = false">
          (character.chat)
        </Link>
        <button @click="isMobileMenuOpen = false" class="text-gray-400 lg:hidden">
          <X :size="22" />
        </button>
      </div>

      <nav class="mb-8 flex flex-col gap-1">
        <Link
          :href="route('characters.create')"
          @click="isMobileMenuOpen = false"
          class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-200 transition hover:bg-bg-card"
          :class="{ 'border-l-2 border-accent bg-bg-card': $page.url.startsWith('/characters/create') }"
        >
          <Plus :size="18" />
          Create
        </Link>
        <Link
          :href="route('dashboard')"
          @click="isMobileMenuOpen = false"
          class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-200 transition hover:bg-bg-card"
          :class="{ 'border-l-2 border-accent bg-bg-card': $page.url === '/dashboard' }"
        >
          <BookOpen :size="18" />
          Your Character
        </Link>
      </nav>

      <div class="mb-3 text-sm font-medium text-gray-400">Recent Chats</div>
      <div class="relative mb-4">
        <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
        <input
          v-model="sidebarSearch"
          type="text"
          placeholder="Search conversations..."
          class="w-full rounded-lg bg-bg-input py-2 pl-9 pr-3 text-sm text-gray-200 placeholder-gray-500 outline-none ring-1 ring-border-subtle focus:ring-accent"
        />
      </div>

      <div class="flex-1 overflow-y-auto">
        <button
          v-for="chat in filteredChats"
          :key="chat.chatId"
          @click="goToChat(chat.characterId)"
          class="mb-1 flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-bg-card"
        >
          <img
            v-if="chat.avatarUrl"
            :src="chat.avatarUrl"
            class="h-9 w-9 flex-shrink-0 rounded-full object-cover"
          />
          <div v-else class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-bg-card text-xs text-gray-400">
            {{ chat.characterName.charAt(0) }}
          </div>
          <div class="min-w-0 flex-1">
            <div class="truncate text-sm font-medium text-gray-100">{{ chat.characterName }}</div>
            <div class="truncate text-xs text-gray-500">{{ chat.lastMessagePreview }}</div>
          </div>
        </button>

        <p v-if="filteredChats.length === 0" class="px-2 py-4 text-center text-xs text-gray-500">
          Belum ada percakapan.
        </p>
      </div>

      <div class="mt-4 flex items-center justify-between border-t border-border-subtle pt-4 text-sm text-gray-300">
        <div class="flex items-center gap-2">
          <UserIcon :size="18" />
          {{ username }}
        </div>
        <button
          @click="logout"
          title="Logout"
          class="rounded-lg p-1.5 text-gray-400 transition hover:bg-bg-card hover:text-red-400"
        >
          <LogOut :size="16" />
        </button>
      </div>
    </aside>

    <!-- Wrapper kanan: topbar mobile + konten -->
    <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
      <!-- Topbar khusus mobile (hamburger), tersembunyi di desktop -->
      <div class="flex items-center gap-3 border-b border-border-subtle bg-bg-panel px-4 py-3 lg:hidden">
        <button @click="isMobileMenuOpen = true" class="text-gray-300">
          <Menu :size="22" />
        </button>
        <span class="text-base font-bold text-accent">(character.chat)</span>
      </div>

      <main class="flex-1 overflow-y-auto">
        <slot />
      </main>
    </div>
  </div>
</template>