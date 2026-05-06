import { useModal } from '@/composables/useModal';
import { usePane } from '@/composables/usePane';
import { useAjax } from '@/composables/useAjax';
import DeleteForm from '@/components/forms/account_payments/DeleteForm.vue';
import SavingAccountPaymentForm from '@/components/forms/account_payments/SavingAccountPaymentForm.vue';
import { ref, shallowRef, toRef, type Component, type Ref } from 'vue';
import { dispatchNotification } from '@/components/notification';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { router, usePage } from '@inertiajs/vue3';
import ViewForm from '@/components/forms/account_payments/ViewForm.vue';

let formApi: { getFormData: () => FormData | null } | null = null;

export interface AccountPayment {
  id?: number
  user_id?: number
  deposit_date?: string
  mode_of_payment?: number
  mode_of_payment_value?: number
  remittance_advice?: string
  remittance_advice_preview_token?: string | null
  remarks?: string
  created_by?: string
  created_at?: string
  deleted_at?: string | null
}

/** Client-side overlays for list rows until the next Inertia `account_payments` refresh (module singleton). */
const listRowPatches: Ref<Record<number, Record<string, unknown>>> = ref({});

export function patchListRow(id: number, patch: Record<string, unknown>): void {
  if (typeof id !== 'number' || !Number.isFinite(id)) return;
  const key = id;
  const prev = listRowPatches.value[key] ?? {};
  listRowPatches.value = {
    ...listRowPatches.value,
    [key]: { ...prev, ...patch },
  };
}

export function clearListRowPatches(): void {
  listRowPatches.value = {};
}

export function useAccountPayments() {
  const page = usePage();
  const { slug } = useModulePermissions();
  const { openModal, closeModal } = useModal();
  const {
    openPane,
    closePane,
    setPaneLoading,
    setPaneError,
    setPaneContent,
    rightPane,
    topPane,
  } = usePane();
  const { get, post } = useAjax();
  const auth = (page.props.auth);
  const rightPaneVisible = toRef(rightPane, 'open');
  const rightPaneTitle = toRef(rightPane, 'title');
  const rightPaneLoading = toRef(rightPane, 'loading');
  const rightPaneError = toRef(rightPane, 'error');
  const rightPaneContentComponent = toRef(rightPane, 'contentComponent');
  const rightPaneComponentProps = toRef(rightPane, 'componentProps');

  const newAccountPayment = async () => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        mode_of_payment_options: Array<{ value: number | string; name: string }>;
      }>(
        `/${slug.value}/create`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch data');
      }

      const payload = response.data;

      if (!payload) return;
      openModal({
        modalTitle: 'Account Payment Form',
        buttonText: 'Save',
        component: SavingAccountPaymentForm,
        componentProps: {
          mode_of_payment_options: payload.mode_of_payment_options,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api;
          }
        },
        size: 'lg',
        onSubmit: async () => {
          if (!formApi) return;
          const formData = formApi.getFormData();
          if (!formData) return;

          showLoader();
          try {
            const response = await post(`/${slug.value}/store`, formData);

            if (!response.ok) {
              dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' });
            } else {
              dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });
              closeModal();
              router.get(window.location.pathname, {}, { preserveState: false, preserveScroll: true, replace: true });
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
          } finally {
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  const editAccountPayment = async (accountPayment: AccountPayment) => {
    showLoader();
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        account_payment: AccountPayment;
        mode_of_payment_options: Array<{ value: number | string; name: string }>;
      }>(
        `/${slug.value}/${accountPayment.id}/edit`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch account payment data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Edit Account Payment`,
        buttonText: 'Update',
        component: SavingAccountPaymentForm,
        componentProps: {
          account_payment: payload.account_payment,
          mode_of_payment_options: payload.mode_of_payment_options,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api;
          }
        },
        size: 'lg',
        onSubmit: async () => {
          if (!formApi) return;
          const formData = formApi.getFormData();
          if (!formData) return;

          showLoader();
          try {
            const response = await post(`/${slug.value}/update`, formData);

            if (!response.ok) {
              dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' });
            } else {
              dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });
              closeModal();
              router.get(window.location.href, {}, { preserveState: false, preserveScroll: true, replace: true });
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
          } finally {
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    } finally {
      hideLoader();
    }
  };

  const viewAccountPayment = async (accountPayment: AccountPayment) => {
    showLoader();
    try {
      openPane({
        title: `Account Payment Details`,
        side: 'right',
        component: ViewForm,
        componentProps: { account_payment: accountPayment, isViewOnly: true },
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    } finally {
      hideLoader();
    }
  };

  const deleteAccountPayment = async (accountPayment: AccountPayment) => {
    const deleteOrRestore = accountPayment.deleted_at ? 'Restore' : 'Delete';
    const color = accountPayment.deleted_at ? 'green' : 'red';
    const buttonClass = `bg-${color}-600
      hover:bg-${color}-700
      focus:ring-${color}-500
      dark:bg-${color}-500
      dark:hover:bg-${color}-600`;

    openModal({
      modalTitle: `${deleteOrRestore} Remittance Advice`,
      buttonText: deleteOrRestore,
      buttonClass: buttonClass,
      component: DeleteForm,
      componentProps: {
        accountPayment: accountPayment,
      },
      size: 'sm',
      onSubmit: async () => {
        showLoader();
        try {
          const formData = new FormData();
          formData.append('id', String(accountPayment.id));

          const response = await post(`/${slug.value}/destroy`, formData);

          if (!response.ok) {
            dispatchNotification({ title: 'Error', content: response.data.message ?? 'Failed to delete', type: 'error' });
          } else {
            dispatchNotification({ title: 'Success', content: response.data.message ?? 'Deleted successfully', type: 'success' });
            closeModal();
            router.get(window.location.pathname, {}, { preserveState: false, preserveScroll: true, replace: true });
          }
        } catch (err) {
          dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
        } finally {
          hideLoader();
        }
      }
    });
  };

  const previewFile = async (accountPayment: AccountPayment) => {
    if (accountPayment.remittance_advice_preview_token) {
      window.open(`/account_payments/preview_file?token=${encodeURIComponent(accountPayment.remittance_advice_preview_token)}`, '_blank', 'noopener,noreferrer');
    }
  };

  return {
    newAccountPayment,
    viewAccountPayment,
    editAccountPayment,
    deleteAccountPayment,
    previewFile,
    closePane,
    rightPaneVisible,
    rightPaneTitle,
    rightPaneLoading,
    rightPaneError,
    rightPaneContentComponent,
    rightPaneComponentProps,
  };
}
