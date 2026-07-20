<script setup>
import { ref, computed, nextTick } from 'vue';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    chats: Array,
});

const chatList = ref(props.chats);
const historySearch = ref('');
const activeChatId = ref(null);
const messages = ref([]);
const inputMessage = ref('');
const isLoading = ref(false);
const messagesContainer = ref(null);

const filteredChats = computed(() => {
    if (!historySearch.value) return chatList.value;
    const keyword = historySearch.value.toLowerCase();
    return chatList.value.filter((chat) => chat.title.toLowerCase().includes(keyword));
});

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

const openChat = async (chatId) => {
    activeChatId.value = chatId;
    const { data } = await axios.get(`/ai-chat/${chatId}`);
    messages.value = data.messages;
    scrollToBottom();
};

const newChat = () => {
    activeChatId.value = null;
    messages.value = [];
};

const sendMessage = async () => {
    const text = inputMessage.value.trim();
    if (!text || isLoading.value) return;

    messages.value.push({ role: 'user', content: text });
    inputMessage.value = '';
    isLoading.value = true;
    scrollToBottom();

    try {
        const { data } = await axios.post('/ai-chat/send', {
            chat_id: activeChatId.value,
            message: text,
        });

        messages.value.push({ role: 'assistant', content: data.answer, ai_provider: data.provider });

        if (!activeChatId.value) {
            activeChatId.value = data.chat_id;
            chatList.value.unshift({ id: data.chat_id, title: data.chat_title, created_at: new Date().toISOString() });
        }
    } catch (error) {
        messages.value.push({ role: 'assistant', content: 'Maaf, terjadi kesalahan saat menghubungi asisten AI.' });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
};
</script>

<template>
    <AppLayout title="Tanya AI">
        <template #chat-history>
            <div class="px-1 py-2">
                <input
                    v-model="historySearch"
                    type="text"
                    placeholder="Cari percakapan..."
                    class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-toline"
                />
            </div>
            <button
                v-for="chat in filteredChats"
                :key="chat.id"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs"
                :class="activeChatId === chat.id ? 'bg-toline-light text-toline-dark' : 'text-slate-500 hover:bg-slate-50'"
                @click="openChat(chat.id)"
            >
                <span>🕘</span>
                <span class="truncate">{{ chat.title }}</span>
            </button>
            <p v-if="filteredChats.length === 0" class="px-3 py-2 text-xs text-slate-400">Tidak ada percakapan ditemukan.</p>
        </template>

        <div class="flex h-[calc(100vh-3rem)] flex-col rounded-xl bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-green-500"></span>
                    <span class="font-semibold text-slate-800">ToLine Assistant</span>
                </div>
                <button class="text-xs font-medium text-toline" @click="newChat">+ Percakapan Baru</button>
            </div>

            <div ref="messagesContainer" class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                <div v-if="messages.length === 0" class="flex flex-col items-center gap-3 pt-10 text-center text-slate-400">
                    <p class="text-sm">
                        Halo! Saya adalah asisten pintar ToLine. Pilih peran Anda untuk memulai percakapan yang lebih personal.
                    </p>
                    <div class="flex gap-3">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-xs font-medium hover:bg-slate-50"
                            @click="inputMessage = 'Saya sebagai Penjual, bagaimana performa toko saya bulan ini?'; sendMessage()"
                        >
                            🏪 Saya sebagai Penjual
                        </button>
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-xs font-medium hover:bg-slate-50"
                            @click="inputMessage = 'Saya sebagai Pembeli, item apa yang paling banyak diminati?'; sendMessage()"
                        >
                            🛒 Saya sebagai Pembeli
                        </button>
                    </div>
                </div>

                <div
                    v-for="(msg, idx) in messages"
                    :key="idx"
                    class="flex"
                    :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-md rounded-2xl px-4 py-2.5 text-sm"
                        :class="msg.role === 'user' ? 'bg-toline text-white' : 'bg-slate-100 text-slate-700'"
                    >
                        {{ msg.content }}
                    </div>
                </div>

                <div v-if="isLoading" class="flex justify-start">
                    <div class="rounded-2xl bg-slate-100 px-4 py-2.5 text-sm text-slate-400">Mengetik...</div>
                </div>
            </div>

            <form class="flex items-center gap-2 border-t border-slate-100 p-4" @submit.prevent="sendMessage">
                <input
                    v-model="inputMessage"
                    type="text"
                    placeholder="Ketik pesan Anda di sini..."
                    class="flex-1 rounded-full border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-toline"
                />
                <button
                    type="submit"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-toline text-white hover:bg-toline-dark"
                >
                    ➤
                </button>
            </form>
        </div>
    </AppLayout>
</template>