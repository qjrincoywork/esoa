<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import { useModal } from '@/composables/useModal';

const {
  visible,
  title,
  buttonLabel,
  buttonClass,
  contentComponent,
  componentProps,
  size,
  hasSubmitButton,
  closeOnClickOutside,
  closeModal,
  submitModal,
} = useModal();
</script>

<template>
  <Modal
    :show="visible"
    :size="size"
    :close-on-click-outside="closeOnClickOutside"
    :modal-title="title"
    :modal-button-label="buttonLabel"
    @close="closeModal"
    @submit="submitModal">
    <template #title>
      {{ title }}
    </template>

    <component
      v-if="contentComponent"
      :is="contentComponent"
      v-bind="componentProps" />

    <template #footer>
      <div class="flex w-full flex-wrap justify-end gap-2 sm:gap-3">
        <button
          type="button"
          class="cursor-pointer rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
          @click="closeModal">
          {{ hasSubmitButton ? 'Cancel' : 'Close' }}
        </button>
        <button
          v-if="hasSubmitButton"
          type="button"
          class="cursor-pointer rounded-lg px-4 py-2 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2"
          :class="buttonClass"
          @click="submitModal">
          {{ buttonLabel }}
        </button>
      </div>
    </template>
  </Modal>
</template>


