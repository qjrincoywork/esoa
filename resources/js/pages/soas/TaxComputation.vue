<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from "@/components/ui/button";
import { useSoas } from '@/composables/soas';
import { useModulePermissions } from '@/composables/useModulePermissions';
const { recomputeTax } = useSoas();

const { slug } = useModulePermissions();
const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'Tax Computation',
    href: slug.value,
  },
];
const props = defineProps({
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

// Expose a form ref so parent components can access without document.getElementById
const recomputeForm = ref<HTMLFormElement | null>(null)

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
  if (!recomputeForm.value) return null
  console.log('recomputeForm', new FormData(recomputeForm.value), recomputeForm.value);
  return new FormData(recomputeForm.value)
}

// defineExpose({
//   recomputeForm,
//   getFormData,
// })

onMounted(() => {
  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData, formRef: recomputeForm.value })
  }
})
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <div class="px-4 py-6">
      <Heading
        title="Recompute Tax"
        description="Manage SOA Tax Computation"
      />
      <div class="flex-1 md:max-w-full">
        <section class="max-w-xl space-y-12">
          <div class="flex flex-col space-y-6">
            <Form
              ref="recomputeForm"
              class="space-y-6"
            >
              <div class="grid gap-2">
                <Label for="ref_id">Billing Reference No.</Label>
                <Input
                  id="ref_id"
                  class="mt-1 block w-full"
                  name="ref_id"
                  required
                  autocomplete="ref_id"
                  placeholder="Billing Reference No."
                />
              </div>
            </Form>

            <div class="flex items-center gap-4">
              <Button class="cursor-pointer" @click="recomputeTax(getFormData())" data-test="recompute-tax-button">Submit</Button>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
