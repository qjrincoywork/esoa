import { ref } from 'vue';

// Global loader state and helpers
export const isLoading = ref(false);

export function showLoader() {
  isLoading.value = true;
}

export function hideLoader() {
  isLoading.value = false;
}

export default {
  isLoading,
  showLoader,
  hideLoader,
};
