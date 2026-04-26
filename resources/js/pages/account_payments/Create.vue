<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SavingAccountPaymentForm from '@/components/forms/account_payments/SavingAccountPaymentForm.vue';

function handleSubmit(formData: FormData) {
  router.post('/account_payments/store', formData);
}
</script>

<template>
  <AppLayout title="Create Account Payment">
    <Head title="Create Account Payment" />

    <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
      <SavingAccountPaymentForm @submit="handleSubmit" />
    </div>
  </AppLayout>
</template>
