<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SavingConcernForm from '@/components/forms/concerns/SavingConcernForm.vue';

const page = usePage();
const concern = (page.props as any).concern;

function handleSubmit(formData: FormData) {
  router.put(`/concerns/${concern.id}`, formData);
}
</script>

<template>
  <AppLayout title="Edit Concern">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Edit Concern
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <SavingConcernForm :concern="concern" @submit="handleSubmit" />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
