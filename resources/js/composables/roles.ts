import { useModal } from '@/composables/useModal';
import { useAjax } from '@/composables/useAjax';
import DeleteForm from '@/components/forms/roles/DeleteForm.vue';
import SavingForm from '@/components/forms/roles/SavingForm.vue';
let formApi: { getFormData: () => FormData | null } | null = null;
import { dispatchNotification } from '@/components/notification';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { router } from '@inertiajs/vue3';

export interface Role {
  id?: number | string;
  name?: string;
  guard_name?: string;
  [key: string]: any;
}

export interface Suffixes {
  id?: number | string;
  name?: string;
  [key: string]: any;
}

export function useRoles() {
  const { slug } = useModulePermissions();
  const { openModal, closeModal } = useModal();
  const { get, post } = useAjax();

  const editRole = async (role: Role) => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        role: Role;
      }>(
        `/${slug.value}/${role.id}/edit`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch Role data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Edit ${payload.role?.name || 'Role'}`,
        buttonText: 'Update',
        component: SavingForm,
        componentProps: {
          role: payload.role,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api
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
              //To be Updated the showing of validation errors in the form
              dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' });
            } else {
              dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });
              closeModal();
              // Refresh current page to update datatable props
              router.get(window.location.pathname, {}, { preserveState: false, preserveScroll: true, replace: true });
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
            console.error(err);
          } finally {
            // Refresh current page to update datatable props
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Internal Server Error', type: 'error' });
      console.error('Internal Server Error:', error);
    }
  };

  const createRole = async () => {
    try {
      openModal({
        modalTitle: `Create Role`,
        buttonText: 'Save',
        component: SavingForm,
        componentProps: {
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api
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
              //To be Updated the showing of validation errors in the form
              dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' });
            } else {
              dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });
              closeModal();
              // Refresh current page to update datatable props
              router.get(window.location.pathname, {}, { preserveState: false, preserveScroll: true, replace: true });
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
            console.error(err);
          } finally {
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Internal Server Error', type: 'error' });
      console.error('Internal Server Error:', error);
    }
  };

  const deleteRole = async (role: Role) => {
    try {
      openModal({
        modalTitle: `Delete ${role?.name || ' Role'}`,
        buttonText: 'Delete',
        buttonClass: 'bg-red-600 hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600',
        component: DeleteForm,
        componentProps: {
          role: role,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api
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
              // Refresh current page to update datatable props
              router.get(window.location.pathname, {}, { preserveState: false, preserveScroll: true, replace: true });
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
            console.error(err);
          } finally {
            hideLoader();
          }
        }
      });
    } catch (error) {
      console.error('Internal Server Error:', error);
    }
  };

  return {
    editRole,
    createRole,
    deleteRole,
  };
}

