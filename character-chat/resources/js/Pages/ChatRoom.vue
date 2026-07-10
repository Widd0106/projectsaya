<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Send, Sparkles, Trash2, Pencil, X, Eraser, RefreshCw, Check } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  character: { type: Object, required: true },
  chatId: { type: Number, required: true },
  messages: { type: Array, default: () => [] },
});

const messages = ref([...props.messages]);
const newMessage = ref('');
const isSending = ref(false);
const regeneratingId = ref(null);
const editingId = ref(null);
const editingContent = ref('');
const isEditSending = ref(false);
const showDeleteConfirm = ref(false);
const showClearConfirm = ref(false);
const scrollContainer = ref(null);

const anyBusy = computed(() => isSending.value || regeneratingId.value !== null || isEditSending.value);

function scrollToBottom() {
  nextTick(() => {
    if (scrollContainer.value) {
      scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
    }
  });
}

onMounted(scrollToBottom);

function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Kirim request ke endpoint streaming (SSE) manapun (send/regenerate/edit)
 * dan kembalikan Response mentah, siap dibaca lewat consumeStream().
 */
async function streamFetch(url, method, body) {
  return fetch(url, {
    method,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'text/event-stream',
      'X-CSRF-TOKEN': getCsrfToken(),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
}

/**
 * Baca body Response sebagai Server-Sent Events, parse tiap blok "data: {...}",
 * lalu panggil handler yang sesuai berdasarkan field "type" di payload-nya.
 * Dipakai bareng oleh sendMessage / regenerate / saveEdit di bawah.
 */
async function consumeStream(response, handlers) {
  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';

  while (true) {
    const { value, done } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });

    let sepIndex;
    while ((sepIndex = buffer.indexOf('\n\n')) !== -1) {
      const rawEvent = buffer.slice(0, sepIndex);
      buffer = buffer.slice(sepIndex + 2);

      for (const line of rawEvent.split('\n')) {
        const trimmed = line.trim();
        if (!trimmed.startsWith('data:')) continue;

        const dataStr = trimmed.slice(5).trim();
        if (!dataStr) continue;

        let payload;
        try {
          payload = JSON.parse(dataStr);
        } catch (e) {
          continue;
        }

        handlers[payload.type]?.(payload);
      }
    }
  }
}

async function sendMessage() {
  const text = newMessage.value.trim();
  if (!text || anyBusy.value) return;

  isSending.value = true;
  newMessage.value = '';

  const tempId = `temp-${Date.now()}`;
  messages.value.push({ id: tempId, role: 'user', content: text });
  scrollToBottom();

  let aiIndex = null;

  try {
    const response = await streamFetch(route('chat.message.stream', props.chatId), 'POST', { message: text });
    if (!response.ok || !response.body) throw new Error('stream gagal');

    await consumeStream(response, {
      user_message: (payload) => {
        const idx = messages.value.findIndex((m) => m.id === tempId);
        if (idx !== -1) messages.value[idx] = payload.message;
      },
      delta: (payload) => {
        if (aiIndex === null) {
          messages.value.push({ id: `streaming-${Date.now()}`, role: 'assistant', content: '' });
          aiIndex = messages.value.length - 1;
        }
        messages.value[aiIndex].content += payload.delta;
        scrollToBottom();
      },
      done: (payload) => {
        if (aiIndex !== null) messages.value[aiIndex] = payload.aiMessage;
        else messages.value.push(payload.aiMessage);
      },
    });
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

// Regenerate cuma diizinkan untuk balasan AI yang paling terakhir,
// supaya riwayat chat tetap linear & konsisten.
function isLastAssistantMessage(msg) {
  if (msg.role !== 'assistant' || typeof msg.id !== 'number') return false;
  const lastAssistant = [...messages.value].reverse().find((m) => m.role === 'assistant' && typeof m.id === 'number');
  return !!lastAssistant && lastAssistant.id === msg.id;
}

async function regenerate(msg) {
  if (anyBusy.value) return;

  const targetIndex = messages.value.findIndex((m) => m.id === msg.id);
  if (targetIndex === -1) return;

  regeneratingId.value = msg.id;
  messages.value[targetIndex] = { ...messages.value[targetIndex], content: '' };

  try {
    const response = await streamFetch(
      route('chat.message.regenerate', { chat: props.chatId, message: msg.id }),
      'POST',
    );
    if (!response.ok || !response.body) throw new Error('regenerate gagal');

    await consumeStream(response, {
      delta: (payload) => {
        messages.value[targetIndex].content += payload.delta;
        scrollToBottom();
      },
      done: (payload) => {
        messages.value[targetIndex] = payload.aiMessage;
      },
    });
  } catch (error) {
    messages.value[targetIndex].content = 'Maaf, gagal regenerate balasan. Coba lagi.';
  } finally {
    regeneratingId.value = null;
    scrollToBottom();
  }
}

function startEdit(msg) {
  if (anyBusy.value) return;
  editingId.value = msg.id;
  editingContent.value = msg.content;
}

function cancelEdit() {
  editingId.value = null;
  editingContent.value = '';
}

async function saveEdit(msg) {
  const text = editingContent.value.trim();
  if (!text || isEditSending.value) return;

  const targetIndex = messages.value.findIndex((m) => m.id === msg.id);
  if (targetIndex === -1) return;

  isEditSending.value = true;
  editingId.value = null;

  try {
    const response = await streamFetch(
      route('chat.message.update', { chat: props.chatId, message: msg.id }),
      'PUT',
      { message: text },
    );
    if (!response.ok || !response.body) throw new Error('edit gagal');

    let aiIndex = null;

    await consumeStream(response, {
      edited: (payload) => {
        // Ganti pesan yang diedit, buang semua pesan setelahnya (riwayat lama sudah tidak relevan)
        messages.value.splice(targetIndex, messages.value.length - targetIndex, payload.message);
      },
      delta: (payload) => {
        if (aiIndex === null) {
          messages.value.push({ id: `streaming-${Date.now()}`, role: 'assistant', content: '' });
          aiIndex = messages.value.length - 1;
        }
        messages.value[aiIndex].content += payload.delta;
        scrollToBottom();
      },
      done: (payload) => {
        if (aiIndex !== null) messages.value[aiIndex] = payload.aiMessage;
        else messages.value.push(payload.aiMessage);
      },
    });
  } catch (error) {
    // Scope portofolio: tidak perlu rollback kompleks, cukup biarkan apa adanya
  } finally {
    isEditSending.value = false;
    scrollToBottom();
  }
}

function confirmDelete() {
  router.delete(route('characters.destroy', props.character.id), {
    onSuccess: () => router.visit(route('dashboard')),
  });
}

async function deleteMessage(messageId) {
  const idx = messages.value.findIndex((m) => m.id === messageId);
  if (idx === -1) return;

  const removedMessage = messages.value[idx];
  messages.value.splice(idx, 1);

  try {
    await fetch(route('chat.message.destroy', { chat: props.chatId, message: messageId }), {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
    });
  } catch (error) {
    messages.value.splice(idx, 0, removedMessage);
  }
}

function askClearChat() {
  showClearConfirm.value = true;
}

async function confirmClearChat() {
  try {
    const response = await fetch(route('chat.clear', props.chatId), {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
    });
    const data = await response.json();
    messages.value = [data.greetingMessage];
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
            <div class="min-w-0 w-full">
              <!-- Mode edit (khusus pesan user) -->
              <div v-if="editingId === msg.id" class="flex flex-col gap-2">
                <textarea
                  v-model="editingContent"
                  rows="2"
                  class="w-full min-w-[220px] rounded-xl border border-accent/60 bg-bg-input px-3 py-2 text-sm text-white outline-none"
                  @keydown.enter.exact.prevent="saveEdit(msg)"
                  @keydown.esc.prevent="cancelEdit"
                ></textarea>
                <div class="flex justify-end gap-2">
                  <button @click="cancelEdit" class="rounded-full p-1.5 text-gray-400 transition hover:bg-bg-card hover:text-white">
                    <X :size="14" />
                  </button>
                  <button @click="saveEdit(msg)" class="rounded-full bg-accent p-1.5 text-white transition hover:opacity-90">
                    <Check :size="14" />
                  </button>
                </div>
              </div>

              <!-- Mode tampil normal -->
              <template v-else>
                <div class="flex items-center gap-1" :class="{ 'flex-row-reverse': msg.role === 'user' }">
                  <div
                    class="rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed sm:px-4 sm:py-3"
                    :class="msg.role === 'user'
                      ? 'border border-accent/60 bg-bg-card text-gray-100'
                      : 'bg-bg-card text-gray-100'"
                  >
                    {{ msg.content }}<span v-if="msg.content === '' && regeneratingId === msg.id" class="animate-pulse text-gray-500">…</span>
                  </div>

                  <div class="flex flex-shrink-0 items-center gap-0.5 opacity-60 transition sm:opacity-0 sm:group-hover:opacity-100">
                    <!-- Regenerate: cuma di balasan AI paling terakhir -->
                    <button
                      v-if="isLastAssistantMessage(msg)"
                      @click="regenerate(msg)"
                      :disabled="anyBusy"
                      title="Regenerate balasan ini"
                      class="rounded-full p-1.5 text-gray-600 transition hover:bg-bg-card hover:text-accent disabled:opacity-40"
                    >
                      <RefreshCw :size="14" :class="{ 'animate-spin': regeneratingId === msg.id }" />
                    </button>

                    <!-- Edit: cuma di pesan user -->
                    <button
                      v-if="msg.role === 'user' && typeof msg.id === 'number'"
                      @click="startEdit(msg)"
                      :disabled="anyBusy"
                      title="Edit pesan ini"
                      class="rounded-full p-1.5 text-gray-600 transition hover:bg-bg-card hover:text-white disabled:opacity-40"
                    >
                      <Pencil :size="14" />
                    </button>

                    <button
                      v-if="typeof msg.id === 'number'"
                      @click="deleteMessage(msg.id)"
                      title="Hapus pesan ini"
                      class="rounded-full p-1.5 text-gray-600 transition hover:bg-bg-card hover:text-red-400"
                    >
                      <X :size="14" />
                    </button>
                  </div>
                </div>
                <div class="mt-1 text-xs text-gray-500" :class="msg.role === 'user' ? 'text-right' : 'text-left'">
                  {{ formatTime(msg.created_at) }}
                </div>
              </template>
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
            :disabled="anyBusy"
            :placeholder="`Message ${character.name}...`"
            class="min-w-0 flex-1 bg-transparent text-sm text-white placeholder-gray-500 outline-none disabled:opacity-50"
          />
          <button
            type="submit"
            :disabled="anyBusy || !newMessage.trim()"
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