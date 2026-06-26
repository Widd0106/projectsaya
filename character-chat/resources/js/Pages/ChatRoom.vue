<script setup>
import { ref, nextTick, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Send, Sparkles, Trash2, Pencil, X, Eraser } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  character: { type: Object, required: true },
  chatId: { type: Number, required: true },
  messages: { type: Array, default: () => [] },
});

const messages = ref([...props.messages]);
const newMessage = ref('');
const isSending = ref(false);
const showDeleteConfirm = ref(false);
const showClearConfirm = ref(false);
const scrollContainer = ref(null);

function scrollToBottom() {
  nextTick(() => {
    if (scrollContainer.value) {
      scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
    }
  });
}

onMounted(scrollToBottom);

async function sendMessage() {
  const text = newMessage.value.trim();
  if (!text || isSending.value) return;

  isSending.value = true;

  // Tampilkan pesan user langsung (optimistic update)
  const tempId = `temp-${Date.now()}`;
  messages.value.push({ id: tempId, role: 'user', content: text });
  newMessage.value = '';
  scrollToBottom();

  try {
    const response = await axios.post(route('chat.message', props.chatId), { message: text });
    // Ganti pesan sementara dengan data resmi dari server, lalu tambahkan balasan AI
    const idx = messages.value.findIndex((m) => m.id === tempId);
    if (idx !== -1) messages.value[idx] = response.data.userMessage;
    messages.value.push(response.data.aiMessage);
  } catch (error) {
    messages.value.push({
      id: `error-${Date.now()}`,
      role: 'assistant',
      content: 'Maaf, terjadi kesalahan saat menghubungi server. Coba lagi.',
    });
  } finally {
    isSending.value = false;
    scrollToBottom();
  }
}

function confirmDelete() {
  router.delete(route('characters.destroy', props.character.id), {
    onSuccess: () => router.visit(route('dashboard')),
  });
}

async function deleteMessage(messageId) {
  // Optimistic update: hapus dari tampilan dulu sebelum konfirmasi server,
  // supaya terasa instan. Kalau gagal, kembalikan ke daftar.
  const idx = messages.value.findIndex((m) => m.id === messageId);
  if (idx === -1) return;

  const removedMessage = messages.value[idx];
  messages.value.splice(idx, 1);

  try {
    await axios.delete(route('chat.message.destroy', { chat: props.chatId, message: messageId }));
  } catch (error) {
    // Gagal hapus di server -> kembalikan pesan ke posisi semula
    messages.value.splice(idx, 0, removedMessage);
  }
}

function askClearChat() {
  showClearConfirm.value = true;
}

async function confirmClearChat() {
  try {
    const response = await axios.delete(route('chat.clear', props.chatId));
    // Setelah dibersihkan, server otomatis mengirim ulang greeting sebagai pesan baru
    messages.value = [response.data.greetingMessage];
  } catch (error) {
    // Tidak perlu aksi khusus, modal akan tetap tertutup dan user bisa coba lagi manual
  } finally {
    showClearConfirm.value = false;
    scrollToBottom();
  }
}

function formatTime(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}
</script>

<template>
  <AppLayout>
    <div class="flex h-full flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-border-subtle px-3 py-3 sm:px-6 sm:py-4">
        <div class="flex min-w-0 items-center gap-2 sm:gap-3">
          <img
            v-if="character.avatar_url"
            :src="character.avatar_url"
            class="h-9 w-9 flex-shrink-0 rounded-full object-cover sm:h-10 sm:w-10"
          />
          <div v-else class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-bg-card text-sm text-gray-400 sm:h-10 sm:w-10">
            {{ character.name.charAt(0) }}
          </div>
          <div class="min-w-0">
            <h2 class="truncate text-sm font-semibold text-white">{{ character.name }}</h2>
            <div class="flex items-center gap-1.5 text-xs text-accent">
              <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
              Online
            </div>
          </div>
        </div>

        <div class="flex flex-shrink-0 items-center gap-1 sm:gap-2">
          <button @click="askClearChat" title="Clear Chat" class="rounded-full p-1.5 text-gray-400 transition hover:bg-bg-card hover:text-yellow-400 sm:p-2">
            <Eraser :size="18" />
          </button>
          <a :href="route('characters.edit', character.id)" class="rounded-full p-1.5 text-gray-400 transition hover:bg-bg-card hover:text-white sm:p-2">
            <Pencil :size="18" />
          </a>
          <button @click="showDeleteConfirm = true" class="rounded-full p-1.5 text-gray-400 transition hover:bg-bg-card hover:text-red-400 sm:p-2">
            <Trash2 :size="18" />
          </button>
        </div>
      </div>

      <!-- Messages -->
      <div ref="scrollContainer" class="flex-1 overflow-y-auto px-3 py-4 sm:px-6 sm:py-6">
        <div class="mx-auto mb-6 flex max-w-md flex-col items-center text-center sm:mb-8">
          <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full border border-border-subtle">
            <Sparkles :size="20" class="text-accent" />
          </div>
          <p class="text-sm text-gray-400">
            You are now in a private session with <span class="font-medium text-accent">{{ character.name }}</span>.
            She remembers your history and evolves through conversation.
          </p>
        </div>

        <div
          v-for="msg in messages"
          :key="msg.id"
          class="group mb-4 flex sm:mb-5"
          :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
        >
          <div class="flex max-w-[85%] items-end gap-2 sm:max-w-lg sm:gap-2.5" :class="{ 'flex-row-reverse': msg.role === 'user' }">
            <img
              v-if="msg.role === 'assistant' && character.avatar_url"
              :src="character.avatar_url"
              class="h-7 w-7 flex-shrink-0 rounded-full object-cover sm:h-8 sm:w-8"
            />
            <div class="min-w-0">
              <div class="flex items-center gap-1" :class="{ 'flex-row-reverse': msg.role === 'user' }">
                <div
                  class="rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed sm:px-4 sm:py-3"
                  :class="msg.role === 'user'
                    ? 'border border-accent/60 bg-bg-card text-gray-100'
                    : 'bg-bg-card text-gray-100'"
                >
                  {{ msg.content }}
                </div>
                <button
                  v-if="typeof msg.id === 'number'"
                  @click="deleteMessage(msg.id)"
                  title="Hapus pesan ini"
                  class="flex-shrink-0 rounded-full p-1.5 text-gray-600 opacity-60 transition hover:bg-bg-card hover:text-red-400 sm:opacity-0 sm:group-hover:opacity-100"
                >
                  <X :size="14" />
                </button>
              </div>
              <div class="mt-1 text-xs text-gray-500" :class="msg.role === 'user' ? 'text-right' : 'text-left'">
                {{ formatTime(msg.created_at) }}
              </div>
            </div>
          </div>
        </div>

        <div v-if="isSending" class="mb-5 flex justify-start">
          <div class="rounded-2xl bg-bg-card px-4 py-3 text-sm text-gray-500">
            {{ character.name }} is typing...
          </div>
        </div>
      </div>

      <!-- Input -->
      <div class="border-t border-border-subtle px-3 py-3 sm:px-6 sm:py-4">
        <form @submit.prevent="sendMessage" class="flex items-center gap-2 rounded-full bg-bg-input px-4 py-2 ring-1 ring-border-subtle focus-within:ring-accent sm:gap-3 sm:px-5">
          <input
            v-model="newMessage"
            type="text"
            :placeholder="`Message ${character.name}...`"
            class="min-w-0 flex-1 bg-transparent text-sm text-white placeholder-gray-500 outline-none"
          />
          <button
            type="submit"
            :disabled="isSending || !newMessage.trim()"
            class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-accent text-white transition hover:opacity-90 disabled:opacity-40"
          >
            <Send :size="16" />
          </button>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4">
      <div class="w-full max-w-sm rounded-2xl border border-border-subtle bg-bg-panel p-6 text-center">
        <h3 class="mb-2 text-lg font-bold text-white">Hapus Karakter?</h3>
        <p class="mb-6 text-sm text-gray-400">
          Tindakan ini akan menghapus <span class="font-medium text-white">{{ character.name }}</span>,
          seluruh riwayat chat, dan gambar avatarnya secara permanen. Tidak bisa dibatalkan.
        </p>
        <div class="flex gap-3">
          <button
            @click="showDeleteConfirm = false"
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

    <!-- Clear Chat Confirmation Modal -->
    <div v-if="showClearConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4">
      <div class="w-full max-w-sm rounded-2xl border border-border-subtle bg-bg-panel p-6 text-center">
        <h3 class="mb-2 text-lg font-bold text-white">Bersihkan Chat?</h3>
        <p class="mb-6 text-sm text-gray-400">
          Semua riwayat percakapan dengan <span class="font-medium text-white">{{ character.name }}</span>
          akan dihapus dan obrolan dimulai ulang dari greeting. Karakternya sendiri tidak akan terhapus.
        </p>
        <div class="flex gap-3">
          <button
            @click="showClearConfirm = false"
            class="flex-1 rounded-full border border-border-subtle py-2.5 text-sm font-medium text-gray-300 transition hover:bg-bg-card"
          >
            Batal
          </button>
          <button
            @click="confirmClearChat"
            class="flex-1 rounded-full bg-yellow-600 py-2.5 text-sm font-semibold text-white transition hover:bg-yellow-700"
          >
            Bersihkan
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>