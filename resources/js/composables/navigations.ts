import { useModal } from '@/composables/useModal';
import { useAjax } from '@/composables/useAjax';
import DeleteForm from '@/components/forms/navigations/DeleteForm.vue';
import SavingForm from '@/components/forms/navigations/SavingForm.vue';
let formApi: { getFormData: () => FormData | null } | null = null;
import { dispatchNotification } from '@/components/notification';
import { useModulePermissions } from '@/composables/useModulePermissions';

export interface Navigation {
  id?: number
  name?: string | number
  label?: string
  icon?: string
  created_by?: number
  status?: number
}

// export interface Suffixes {
//   id?: number | string;
//   name?: string;
//   [key: string]: any;
// }

export function useNavigations() {
  const { slug } = useModulePermissions();
  const { openModal, closeModal } = useModal();
  const { get, post } = useAjax();

  const editNavigation = async (navigation: Navigation) => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        navigation: Navigation;
        statuses: Array<{ value: number | string; name: string }>;
      }>(
        `/${slug.value}/${navigation.id}/edit`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Edit ${payload.navigation?.name || 'Navigation'}`,
        buttonText: 'Update',
        component: SavingForm,
        componentProps: {
          navigation: payload.navigation,
          statuses: payload.statuses,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api
          }
        },
        size: 'lg',
        onSubmit: async () => {
          if (!formApi) return;

          const formData = formApi.getFormData();
          if (!formData) return;

          const response = await post(`/${slug.value}/update`, formData);

          if (!response.ok) {
            //To be Updated the showing of validation errors in the form
            dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' });
          } else {
            dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });
            closeModal();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
      console.error('Error fetching data:', error);
    }
  };

  const createNavigation = async () => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        statuses: Array<{ value: number | string; name: string }>;
      }>(
        `/${slug.value}/create`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Create Navigation`,
        buttonText: 'Save',
        component: SavingForm,
        componentProps: {
          statuses: payload.statuses,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api
          }
        },
        size: 'md',
        onSubmit: async () => {
          if (!formApi) return;

          const formData = formApi.getFormData();
          if (!formData) return;

          const response = await post(`/${slug.value}/store`, formData);

          if (!response.ok) {
            //To be Updated the showing of validation errors in the form
            dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' });
          } else {
            dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });
            closeModal();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
      console.error('Error fetching data:', error);
    }
  };

  const deleteNavigation = async (navigation: Navigation) => {
    try {
      openModal({
        modalTitle: `Delete ${navigation?.name || ' Navigation'}`,
        buttonText: 'Delete',
        buttonClass: 'bg-red-600 hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600',
        component: DeleteForm,
        componentProps: {
          navigation: navigation,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api
          }
        },
        size: 'sm',
        onSubmit: async () => {
          if (!formApi) return;
          const formData = formApi.getFormData();
          if (!formData) return;

          const response = await post(`/${slug.value}/destroy`, formData);

          if (!response.ok) {
            dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' });
          } else {
            dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });
            closeModal();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
      console.error('Error fetching data:', error);
    }
  };

  return {
    editNavigation,
    createNavigation,
    deleteNavigation,
  };
}

