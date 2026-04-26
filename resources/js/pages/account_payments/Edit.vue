<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SavingAccountPaymentForm from '@/components/forms/account_payments/SavingAccountPaymentForm.vue';

const page = usePage();
const accountPayment = (page.props as any).account_payment;

function handleSubmit(formData: FormData) {
  router.post('/account_payments/update', formData);
}
</script>

<template>
  <AppLayout title="Edit Account Payment">
    <Head title="Edit Account Payment" />

    <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
      <SavingAccountPaymentForm :account_payment="accountPayment" @submit="handleSubmit" />
    </div>
  </AppLayout>
</template>
