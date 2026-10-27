<script setup lang="ts">
/**
 * The body of an advisory — its image, then its message — exactly as the sign-in
 * pop-up shows it. Shared with the admin preview so what an administrator checks is
 * what users get. The title is left to the surrounding dialog's header.
 */
import { ref, watch } from 'vue';
import { ImageOff } from 'lucide-vue-next';

const props = defineProps<{
  title: string
  message?: string | null
  imageUrl?: string | null
}>();

const imageFailed = ref(false);

watch(() => props.imageUrl, () => {
  imageFailed.value = false;
});
</script>

<template>
  <div class="flex flex-col gap-4">
    <div
      v-if="imageUrl"
      class="flex items-center justify-center overflow-hidden rounded-lg bg-muted"
    >
      <img
        v-if="!imageFailed"
        :src="imageUrl"
        :alt="title"
        class="max-h-[65vh] w-full object-contain"
        @error="imageFailed = true"
      />
      <div v-else class="flex flex-col items-center gap-2 py-12 text-sm text-muted-foreground">
        <ImageOff class="size-8" />
        <span>The advisory image could not be loaded.</span>
      </div>
    </div>
    <p
      v-if="message"
      class="text-sm leading-relaxed whitespace-pre-line text-foreground"
    >{{ message }}</p>
  </div>
</template>
