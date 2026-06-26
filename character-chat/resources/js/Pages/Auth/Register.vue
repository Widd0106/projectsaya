<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { Sparkles, User, Mail, Lock, Eye, EyeOff } from 'lucide-vue-next';

const showPassword = ref(false);

const form = useForm({
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
});

function submit() {
  form.post(route('register'), {
    onError: () => {
      // Error otomatis tersedia lewat form.errors di template
    },
  });
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-bg px-4 py-12">
    <div class="w-full max-w-3xl">
      <div class="mb-8 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-accent">(character.chat)</h1>
          <p class="mt-1 text-sm text-gray-400">Begin your journey with your own AI creations.</p>
        </div>
        <div class="flex gap-3">
          <Link
            :href="route('login')"
            class="rounded-full bg-gradient-to-r from-accent-dark to-accent px-6 py-2 text-sm font-semibold text-white shadow-lg shadow-accent/20 transition hover:opacity-90"
          >
            LOGIN
          </Link>
          <button
            class="rounded-full bg-gradient-to-r from-accent to-accent-light px-6 py-2 text-sm font-semibold text-bg shadow-lg shadow-accent/20"
          >
            SIGN UP
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-8 rounded-2xl border border-border-subtle bg-bg-panel p-8 shadow-2xl md:grid-cols-2">
        <div class="flex flex-col justify-center border-r border-border-subtle pr-8 md:border-r">
          <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-bg-card to-bg-input">
            <Sparkles class="text-accent" :size="26" />
          </div>
          <h2 class="mb-2 text-xl font-bold text-white">Your AI Journey Starts Here</h2>
          <p class="text-sm leading-relaxed text-gray-400">
            Join millions of creators building unique personalities and engaging in deep
            conversations with the world's most advanced AI characters.
          </p>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-4">
          <h3 class="text-lg font-bold text-white">Create Account</h3>

          <div>
            <label class="mb-1.5 block text-sm text-gray-300">Username</label>
            <div class="relative">
              <User :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
              <input
                v-model="form.username"
                type="text"
                placeholder="youngdog_7251"
                class="w-full rounded-lg bg-bg-input py-2.5 pl-9 pr-3 text-sm text-white placeholder-gray-500 outline-none ring-1 ring-border-subtle focus:ring-accent"
              />
            </div>
            <p v-if="form.errors.username" class="mt-1 text-xs text-red-400">{{ form.errors.username }}</p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm text-gray-300">Email Address</label>
            <div class="relative">
              <Mail :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
              <input
                v-model="form.email"
                type="email"
                placeholder="name@example.com"
                class="w-full rounded-lg bg-bg-input py-2.5 pl-9 pr-3 text-sm text-white placeholder-gray-500 outline-none ring-1 ring-border-subtle focus:ring-accent"
              />
            </div>
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm text-gray-300">Password</label>
            <div class="relative">
              <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••"
                class="w-full rounded-lg bg-bg-input py-2.5 pl-9 pr-9 text-sm text-white placeholder-gray-500 outline-none ring-1 ring-border-subtle focus:ring-accent"
              />
              <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                <component :is="showPassword ? EyeOff : Eye" :size="16" />
              </button>
            </div>
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm text-gray-300">Confirm Password</label>
            <input
              v-model="form.password_confirmation"
              :type="showPassword ? 'text' : 'password'"
              placeholder="••••••••"
              class="w-full rounded-lg bg-bg-input px-3 py-2.5 text-sm text-white placeholder-gray-500 outline-none ring-1 ring-border-subtle focus:ring-accent"
            />
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="mt-2 rounded-full bg-gradient-to-r from-accent-dark to-accent py-3 text-sm font-bold uppercase tracking-wide text-white shadow-lg shadow-accent/20 transition hover:opacity-90 disabled:opacity-50"
          >
            {{ form.processing ? 'Creating...' : 'Create Account' }}
          </button>
        </form>
      </div>

      <p class="mt-6 text-center text-sm text-gray-400">
        Already have an account?
        <Link :href="route('login')" class="font-medium text-accent hover:underline">Log in</Link>
      </p>
    </div>
  </div>
</template>
