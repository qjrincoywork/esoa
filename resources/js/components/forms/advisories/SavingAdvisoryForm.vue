<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import FormField from '@/components/FormField.vue';
import type { Advisory } from '@/composables/advisories';

const props = defineProps({
  advisory: {
    type: Object as unknown as () => Advisory,
    default: () => ({}),
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

const advisoryForm = ref<HTMLFormElement | null>(null);
const isEditing = computed(() => props.advisory?.id != null);

const form = ref({
  title: props.advisory?.title ?? '',
  message: props.advisory?.message ?? '',
  starts_at: props.advisory?.starts_at_value ?? '',
  ends_at: props.advisory?.ends_at_value ?? '',
  // A new advisory starts switched on: creating one is usually meant to publish it.
  is_active: isEditing.value ? Number(props.advisory?.is_active) !== 0 : true,
});

/** Preview of the image that will be shown: a newly picked file, else the stored one. */
const pickedImageUrl = ref<string | null>(null);
const imagePreviewUrl = computed(() => pickedImageUrl.value ?? props.advisory?.image_url ?? null);

const onImagePicked = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null;
  if (pickedImageUrl.value) URL.revokeObjectURL(pickedImageUrl.value);
  pickedImageUrl.value = file ? URL.createObjectURL(file) : null;
};

/** Format a datetime-local value ("2026-10-29T04:00") the way people read it. */
const formatWhen = (value: string) => new Date(value).toLocaleString(undefined, {
  dateStyle: 'medium',
  timeStyle: 'short',
});

/** One sentence saying when the advisory will pop up, as the form stands. */
const scheduleSummary = computed(() => {
  if (!form.value.is_active) {
    return 'Disabled — it will not be shown until it is enabled again.';
  }
  const from = form.value.starts_at ? `from ${formatWhen(form.value.starts_at)}` : 'starting now';
  const until = form.value.ends_at ? `until ${formatWhen(form.value.ends_at)}` : 'until it is disabled';
  return `Shown to every user after they sign in, ${from} ${until}.`;
});

function getFormData(): FormData | null {
  if (!advisoryForm.value) return null;
  return new FormData(advisoryForm.value);
}

onMounted(() => {
  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData, formRef: advisoryForm.value });
  }
});

onBeforeUnmount(() => {
  if (pickedImageUrl.value) URL.revokeObjectURL(pickedImageUrl.value);
});
</script>

<template>
  <form ref="advisoryForm" class="grid grid-cols-1 gap-4" enctype="multipart/form-data">
    <div class="hidden">
      <!-- Native hidden inputs so FormData always reflects the latest reactive values -->
      <input v-if="isEditing" type="hidden" name="id" :value="advisory.id" />
      <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0" />
    </div>

    <FormField name="title" label="Title" required>
      <Input
        id="title"
        name="title"
        v-model="form.title"
        placeholder="e.g. Scheduled network maintenance"
        autocomplete="off"
      />
    </FormField>

    <FormField name="image" label="Image">
      <div
        v-if="imagePreviewUrl"
        class="flex items-center justify-center overflow-hidden rounded-lg border bg-muted"
      >
        <img :src="imagePreviewUrl" alt="Advisory image preview" class="max-h-64 w-full object-contain" />
      </div>
      <Input
        id="image"
        name="image"
        type="file"
        accept="image/png,image/jpeg,image/webp"
        @change="onImagePicked"
      />
      <p class="text-xs text-muted-foreground">
        PNG, JPG or WEBP.
        <template v-if="isEditing && advisory.image_url">Leave empty to keep the current image.</template>
      </p>
    </FormField>

    <FormField name="message" label="Message">
      <Textarea
        id="message"
        name="message"
        v-model="form.message"
        rows="4"
        placeholder="Optional text shown below the image."
      />
      <p class="text-xs text-muted-foreground">An advisory needs an image, a message, or both.</p>
    </FormField>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <FormField name="starts_at" label="Show from">
        <Input id="starts_at" name="starts_at" type="datetime-local" v-model="form.starts_at" />
        <p class="text-xs text-muted-foreground">Empty: starts right away.</p>
      </FormField>
      <FormField name="ends_at" label="Show until">
        <Input id="ends_at" name="ends_at" type="datetime-local" v-model="form.ends_at" />
        <p class="text-xs text-muted-foreground">Empty: until it is disabled.</p>
      </FormField>
    </div>

    <FormField name="is_active" for="is_active_switch">
      <div class="flex items-center justify-between gap-4 rounded-lg border p-3">
        <div class="space-y-0.5">
          <span id="is_active_label" class="text-sm font-medium">Enabled</span>
          <p class="text-xs text-muted-foreground">{{ scheduleSummary }}</p>
        </div>
        <Switch
          id="is_active_switch"
          v-model="form.is_active"
          class="shrink-0"
          aria-labelledby="is_active_label"
        />
      </div>
    </FormField>
  </form>
</template>
