import { useModal } from '@/composables/useModal';
import { useAjax } from '@/composables/useAjax';
import DeleteForm from '@/components/forms/permissions/DeleteForm.vue';
import SavingForm from '@/components/forms/permissions/SavingForm.vue';
let formApi: { getFormData: () => FormData | null } | null = null;
import { dispatchNotification } from '@/components/notification';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { router } from '@inertiajs/vue3';

export interface Permission {
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

export function usePermissions() {
    const { slug } = useModulePermissions();
  const { openModal, closeModal } = useModal();
  const { get, post } = useAjax();

  const editPermission = async (permission: Permission) => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        permission: Permission;
      }>(
        `/${slug.value}/${permission.id}/edit`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch Permission data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Edit ${payload.permission?.name || 'Permission'}`,
        buttonText: 'Update',
        component: SavingForm,
        componentProps: {
          permission: payload.permission,
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
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
            console.error(err);
          } finally {
            // Refresh current page to update datatable props
            router.get(window.location.pathname, {}, { preserveState: false, preserveScroll: true, replace: true });
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Internal Server Error', type: 'error' });
      console.error('Internal Server Error:', error);
    }
  };

  const createPermission = () => {
    try {
      openModal({
        modalTitle: `Create Permission`,
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
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
            console.error(err);
          } finally {
            // Refresh current page to update datatable props
            router.get(window.location.pathname, {}, { preserveState: false, preserveScroll: true, replace: true });
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Internal Server Error', type: 'error' });
      console.error('Internal Server Error:', error);
    }
  };

  const deletePermission = async (permission: Permission) => {
    try {
      openModal({
        modalTitle: `Delete ${permission?.name || ' Permission'}`,
        buttonText: 'Delete',
        buttonClass: 'bg-red-600 hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600',
        component: DeleteForm,
        componentProps: {
          permission: permission,
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
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
            console.error(err);
          } finally {
            // Refresh current page to update datatable props
            router.get(window.location.pathname, {}, { preserveState: false, preserveScroll: true, replace: true });
            hideLoader();
          }
        }
      });
    } catch (error) {
      console.error('Internal Server Error:', error);
    }
  };

  return {
    editPermission,
    createPermission,
    deletePermission,
  };
}

