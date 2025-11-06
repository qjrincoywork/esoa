import { ref, shallowRef, type Component } from 'vue';
// import { ref, type Component, markRaw } from 'vue';
import { router } from '@inertiajs/vue3';

export type HttpMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';
export type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl' | '5xl' | 'full';

export interface ModalOptions {
  modalTitle?: string;
  buttonText?: string;
  component?: Component | null;
  componentProps?: Record<string, any>;
  onSubmit?: () => void | Promise<void>;
  submitUrl?: string;
  submitMethod?: HttpMethod;
  submitData?: Record<string, any>;
  size?: ModalSize;
  closeOnClickOutside?: boolean;
}

const visible = ref(false);
const title = ref('');
const buttonLabel = ref('');
const contentComponent = shallowRef<Component | null>(null);
const componentProps = ref<Record<string, any>>({});
const submitAction = ref<(() => void | Promise<void>) | null>(null);
const submitUrl = ref<string | null>(null);
const submitMethod = ref<HttpMethod>('post');
const submitData = ref<Record<string, any>>({});
const size = ref<ModalSize>('md');
const closeOnClickOutside = ref(true);

export function useModal() {
  const openModal = (options: ModalOptions = {}) => {
    title.value = options.modalTitle || 'Modal';
    buttonLabel.value = options.buttonText || 'Submit';
    contentComponent.value = options.component || null;
    componentProps.value = options.componentProps || {};
    submitAction.value = options.onSubmit || null;
    submitUrl.value = options.submitUrl || null;
    submitMethod.value = options.submitMethod || 'post';
    submitData.value = options.submitData || {};
    size.value = options.size || 'md';
    closeOnClickOutside.value = options.closeOnClickOutside ?? true;
    visible.value = true;
  };

  const closeModal = () => {
    visible.value = false;
    // Clear values after animation
    setTimeout(() => {
      title.value = '';
      buttonLabel.value = '';
      contentComponent.value = null;
      componentProps.value = {};
      submitAction.value = null;
      submitUrl.value = null;
      submitMethod.value = 'post';
      submitData.value = {};
      size.value = 'md';
      closeOnClickOutside.value = true;
    }, 150);
  };

  const submitModal = async () => {
    // If custom onSubmit is provided, use it
    if (submitAction.value) {
      await submitAction.value();
      return;
    }

    // Otherwise, use the URL endpoint
    if (submitUrl.value) {
      const method = submitMethod.value.toLowerCase() as HttpMethod;
      const options = {
        preserveScroll: true,
        preserveState: true,
        only: [],
      };

      switch (method) {
        case 'get':
          router.get(submitUrl.value, submitData.value, options);
          break;
        case 'post':
          router.post(submitUrl.value, submitData.value, options);
          break;
        case 'put':
          router.put(submitUrl.value, submitData.value, options);
          break;
        case 'patch':
          router.patch(submitUrl.value, submitData.value, options);
          break;
        case 'delete':
          router.delete(submitUrl.value, options);
          break;
      }
    }
  };

  return {
    visible,
    title,
    buttonLabel,
    contentComponent,
    componentProps,
    size,
    closeOnClickOutside,
    openModal,
    closeModal,
    submitModal,
  };
}
