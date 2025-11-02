<script setup>
import { ref } from 'vue';

const username = ref('')
const email = ref('')
</script>

<template>
  <form class="space-y-3">
    <div>
      <label class="block text-sm font-medium text-gray-700">Username</label>
      <input v-model="username" class="w-full border rounded px-3 py-2" type="text" />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Email</label>
      <input v-model="email" class="w-full border rounded px-3 py-2" type="email" />
    </div>
  </form>
</template>