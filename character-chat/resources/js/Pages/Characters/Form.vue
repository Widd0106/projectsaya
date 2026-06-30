<script setup>
import { ref, computed } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import { Info, Brain, MessageSquare, Camera, Wand2, Trash2, Plus } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  character: { type: Object, default: null },
  mode: { type: String, default: 'create' }, // 'create' | 'edit'
});

const isEdit = computed(() => props.mode === 'edit');

const avatarPreview = ref(props.character?.avatar_url || null);
const avatarFile = ref(null);

const form = useForm({
  name: props.character?.name || '',
  gender: props.character?.gender || '',
  greeting: props.character?.greeting || '',
  avatar: null,
  short_description: props.character?.short_description || '',
  long_description: props.character?.long_description || '',
  example_dialogues: props.character?.example_dialogues?.length
    ? [...props.character.example_dialogues]
    : [{ user: '', ai: '' }],
});

function onAvatarChange(e) {
  const file = e.target.files[0];
  if (!file) return;
  avatarFile.value = file;
  form.avatar = file;
  avatarPreview.value = URL.createObjectURL(file);
}

function addDialogue() {
  form.example_dialogues.push({ user: '', ai: '' });
}

function removeDialogue(index) {
  form.example_dialogues.splice(index, 1);
}

function submit() {
  if (isEdit.value) {
    // Laravel tidak bisa parse multipart PUT secara native lewat fetch biasa,
    // jadi kita pakai method spoofing bawaan Inertia (_method: 'put').
    form.transform((data) => ({ ...data, _method: 'put' })).post(route('characters.update', props.character.id));
  } else {
    form.post(route('characters.store'));
  }
}

function cancel() {
  router.visit(route('dashboard'));
}
</script>

<template>
  <AppLayout>
    <div class="px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
      <div class="mb-6 flex items-center justify-between sm:mb-8">
        <h1 class="text-xl font-bold text-white sm:text-2xl">{{ isEdit ? 'Edit Character' : 'Create Character' }}</h1>
      </div>

      <form @submit.prevent="submit" class="flex flex-col gap-4 sm:gap-6">
        <!-- Basic Info -->
        <section class="rounded-2xl border border-border-subtle bg-bg-panel p-4 sm:p-6">
          <div class="mb-5 flex items-center gap-2 text-accent">
            <Info :size="18" />
            <h2 class="text-sm font-semibold">Basic Info</h2>
          </div>

          <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div> <!--Name input-->
              <label class="mb-1.5 block text-sm text-gray-300">Name</label>
              <input
                v-model="form.name"
                type="text"
                placeholder="e.g. Albert Einstein"
                class="w-full rounded-lg bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-500 outline-none"
              />
              <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
            </div>
            <div> <!--Gender input-->
              <label class="mb-1.5 block text-sm text-gray-300">Gender</label>
              <select
                v-model="form.gender"
                class="w-full rounded-lg bg-white px-3 py-2.5 text-sm text-gray-900 outline-none"
              >
                <option value="">Select gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Non-binary">Non-binary</option>
                <option value="Agender">Agender</option>
                <option value="Bigender">Bigender</option>
                <option value="Genderfluid">Genderfluid</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>

          <div class="mt-5"> <!--Greeting input-->
            <label class="mb-1.5 block text-sm text-gray-300">Greeting</label>
            <textarea
              v-model="form.greeting"
              rows="3"
              placeholder="How does your character introduce themselves?"
              class="w-full rounded-lg bg-bg-input px-3 py-2.5 text-sm text-white placeholder-gray-500 outline-none ring-1 ring-border-subtle focus:ring-accent"
            ></textarea>
            <p class="mt-1.5 text-xs text-gray-500">This is the first message the character will send to start a conversation.</p>
            <p v-if="form.errors.greeting" class="mt-1 text-xs text-red-400">{{ form.errors.greeting }}</p>
          </div>
        </section>

        <div class="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-[1fr_1.4fr]">
          <!-- Character Avatar -->
          <section class="rounded-2xl border border-border-subtle bg-bg-panel p-4 sm:p-6">
            <div class="flex flex-col items-center justify-center py-6 text-center">
              <p class="mb-4 text-sm text-gray-300">Character Avatar</p>
              <label class="relative flex h-32 w-32 cursor-pointer flex-col items-center justify-center rounded-full border-2 border-dashed border-border-subtle text-gray-500 transition hover:border-accent">
                <img v-if="avatarPreview" :src="avatarPreview" class="absolute inset-0 h-full w-full rounded-full object-cover" />
                <template v-else>
                  <Camera :size="24" />
                  <span class="mt-2 text-xs">Upload Character Image</span>
                </template>
                <input type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
              </label>
              <p class="mt-4 text-xs text-gray-500">Recommended: Square 512×512px.</p>
              <p v-if="form.errors.avatar" class="mt-1 text-xs text-red-400">{{ form.errors.avatar }}</p>
            </div>
          </section>

          <!-- Personality & Backstory -->
          <section class="rounded-2xl border border-border-subtle bg-bg-panel p-4 sm:p-6">
            <div class="mb-5 flex items-center gap-2 text-accent">
              <Brain :size="18" />
              <h2 class="text-sm font-semibold">Personality &amp; Backstory</h2>
            </div>

            <div class="mb-5"> <!-- ShortDesc input -->
              <label class="mb-1.5 block text-sm text-gray-300">Short Description</label>
              <input
                v-model="form.short_description"
                type="text"
                placeholder="e.g. A brilliant physicist known for relativity"
                class="w-full rounded-lg bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-500 outline-none"
              />
              <p v-if="form.errors.short_description" class="mt-1 text-xs text-red-400">{{ form.errors.short_description }}</p>
            </div>

            <div> <!-- LongDesc input -->
              <label class="mb-1.5 block text-sm text-gray-300">Long Description / Deep Lore</label>
              <textarea
                v-model="form.long_description"
                rows="6"
                placeholder="Describe their history, motivations, and specific quirks..."
                class="w-full rounded-lg bg-bg-input px-3 py-2.5 text-sm text-white placeholder-gray-500 outline-none ring-1 ring-border-subtle focus:ring-accent"
              ></textarea>
            </div>
          </section>
        </div>

        <!-- Advanced Configuration -->
        <section class="rounded-2xl border border-border-subtle bg-bg-panel p-4 sm:p-6">
          <div class="mb-1 flex items-center gap-2 text-accent">
            <MessageSquare :size="18" />
            <h2 class="text-sm font-semibold">Advanced Configuration</h2>
          </div>
          <p class="mb-5 text-xs text-gray-500">Define example dialogues to train the AI's speaking style.</p>

          <div
            v-for="(dialogue, index) in form.example_dialogues"
            :key="index"
            class="mb-4 rounded-xl border border-border-subtle bg-bg-input p-4"
          >
            <div class="mb-3 flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Example Dialogue #{{ index + 1 }}</span>
              <button type="button" @click="removeDialogue(index)" class="text-gray-500 hover:text-red-400">
                <Trash2 :size="16" />
              </button>
            </div>

            <div class="mb-3"> <!--Contoh Dialog input user-->
              <label class="mb-1 block text-xs text-gray-400">User:</label>
              <input
                v-model="dialogue.user"
                type="text"
                placeholder="What do you think of time?"
                class="w-full rounded-lg bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-500 outline-none"
              />
            </div>
            <div> <!--Contoh Dialog input AI-->
              <label class="mb-1 block text-xs text-gray-400">AI:</label>
              <input
                v-model="dialogue.ai"
                type="text"
                placeholder="Time is an illusion, albeit a very persistent one..."
                class="w-full rounded-lg bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-500 outline-none"
              />
            </div>
          </div>

          <button
            type="button"
            @click="addDialogue"
            class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-border-subtle py-3 text-sm text-gray-400 transition hover:border-accent hover:text-accent"
          >
            <Plus :size="16" />
            Add Dialogue Example
          </button>
        </section>

        <div class="flex items-center justify-between pb-4">
          <button type="button" @click="cancel" class="text-sm font-medium text-red-400 hover:underline">
            Cancel
          </button>
          <button
            type="submit"
            :disabled="form.processing"
            class="rounded-full bg-gradient-to-r from-accent-dark to-accent px-8 py-3 text-sm font-bold text-white shadow-lg shadow-accent/20 transition hover:opacity-90 disabled:opacity-50"
          >
            {{ form.processing ? 'Saving...' : (isEdit ? 'Save Changes' : 'Create Character') }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>