import { useModal } from '@/composables/useModal';
import { useAjax } from '@/composables/useAjax';
import DeleteForm from '@/components/forms/concerns/DeleteForm.vue';
import SavingConcernForm from '@/components/forms/concerns/SavingConcernForm.vue';
import { ref, type Ref } from 'vue';
import { dispatchNotification } from '@/components/notification';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { router, usePage } from '@inertiajs/vue3';

let formApi: { getFormData: () => FormData | null } | null = null;

export interface Concern {
  id?: number
  user_id?: number
  billing_invoice?: string
  type?: string
  title?: string
  description?: string
  status?: string
  attachment?: string
  deleted_at?: string
}

export function useConcerns() {
  const page = usePage();
  const { slug } = useModulePermissions();
  const { openModal, closeModal } = useModal();
  const { get, post } = useAjax();
  const auth = (page.props.auth);

  const newConcern = async () => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        concern_types: Array<{ value: number | string; name: string }>;
        ticket_statuses: Array<{ value: number | string; name: string }>;
      }>(
        `/${slug.value}/create`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch data');
      }

      const payload = response.data;
      console.log(page.props);

      if (!payload) return;
      openModal({
        modalTitle: 'Concern Form',
        buttonText: 'Save',
        component: SavingConcernForm,
        componentProps: {
          concern_types: payload.concern_types,
          ticket_statuses: payload.ticket_statuses ?? [],
          auth: auth ?? undefined,
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

  const editConcern = async (concern: Concern) => {
    showLoader();
    try {
      const response = await get<{
        concern: Concern;
      }>(
        `/${slug.value}/${concern.id}/edit`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch concern data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Edit ${payload.concern?.title || 'Concern'}`,
        buttonText: 'Update',
        component: SavingConcernForm,
        componentProps: {
          concern: payload.concern,
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
    } finally {
      hideLoader();
    }
  };

  const deleteConcern = async (concern: Concern) => {
    const deleteOrRestore = concern.deleted_at ? 'Restore' : 'Delete';
    const color = concern.deleted_at ? 'green' : 'red';

    const buttonClass = `bg-${color}-600
      hover:bg-${color}-700
      focus:ring-${color}-500
      dark:bg-${color}-500
      dark:hover:bg-${color}-600`;

    try {
      openModal({
        modalTitle: `${deleteOrRestore} ${concern?.title || 'Concern'}`,
        buttonText: deleteOrRestore,
        buttonClass: buttonClass,
        component: DeleteForm,
        componentProps: {
          concern: concern,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api;
          }
        },
        size: 'sm',
        onSubmit: async () => {
          if (!formApi) return;
          const formData = formApi.getFormData();
          if (!formData) return;

          showLoader();
          try {
            const response = await post(`/${slug.value}/destroy`, formData);

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

  return {
    newConcern,
    editConcern,
    deleteConcern,
  };
}
