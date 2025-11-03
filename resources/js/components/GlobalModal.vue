<script setup lang="ts">
import Modal from '@/components/Modal.vue';
import { useModal } from '@/composables/useModal';

const {
  visible,
  title,
  buttonLabel,
  contentComponent,
  componentProps,
  size,
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
      <!-- <button
        type="button"
        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
        @click="closeModal">
        Cancel
      </button> -->
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-500 dark:hover:bg-blue-600"
        @click="submitModal">
        {{ buttonLabel }}
      </button>
    </template>
  </Modal>
</template>


