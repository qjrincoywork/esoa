<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SavingConcernForm from '@/components/forms/concerns/SavingConcernForm.vue';

function handleSubmit(formData: FormData) {
  router.post('/concerns', formData);
}
</script>

<template>
  <AppLayout title="Create Concern">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Create Concern
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <SavingConcernForm @submit="handleSubmit" />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
