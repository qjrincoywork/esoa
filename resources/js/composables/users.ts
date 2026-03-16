import { useModal } from '@/composables/useModal';
import { useAjax } from '@/composables/useAjax';
import DeleteForm from '@/components/forms/users/DeleteForm.vue';
import SavingForm from '@/components/forms/users/SavingForm.vue';
let formApi: { getFormData: () => FormData | null } | null = null;
import { dispatchNotification } from '@/components/notification';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { router } from '@inertiajs/vue3';

export interface User {
  id?: number | string;
  username?: string;
  email?: string;
  deleted_at?: string;
  [key: string]: any;
}

export interface Suffixes {
  id?: number | string;
  name?: string;
  [key: string]: any;
}

export function useUsers() {
  const { slug } = useModulePermissions();
  const { openModal, closeModal } = useModal();
  const { get, post } = useAjax();

  const editUser = async (user: User) => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        user: User;
        suffixes: Array<{ id: number | string; name: string }>;
        genders: Array<{ id: number | string; name: string }>;
        types: Array<{ value: number | string; name: string }>;
        account_types: Array<{ value: number | string; name: string }>;
        civil_statuses: Array<{ id: number | string; name: string }>;
        citizenships: Array<{ id: number | string; name: string }>;
        departments: Array<{ id: number | string; name: string }>;
        positions: Array<{ id: number | string; name: string }>;
      }>(
        `/${slug.value}/${user.id}/edit`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch user data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Edit ${payload.user?.username || 'User'}`,
        buttonText: 'Update',
        component: SavingForm,
        componentProps: {
          user: payload.user,
          suffixes: payload.suffixes,
          genders: payload.genders,
          types: payload.types,
          account_types: payload.account_types,
          civil_statuses: payload.civil_statuses,
          citizenships: payload.citizenships,
          departments: payload.departments,
          positions: payload.positions,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api
          }
        },
        size: 'xl2',
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
              router.get(window.location.pathname, {}, { preserveState: false, preserveScroll: true, replace: true });
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
          } finally {
            // Refresh current page to update datatable props
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  const createUser = async () => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        suffixes: Array<{ id: number | string; name: string }>;
        genders: Array<{ id: number | string; name: string }>;
        types: Array<{ value: number | string; name: string }>;
        account_types: Array<{ value: number | string; name: string }>;
        civil_statuses: Array<{ id: number | string; name: string }>;
        citizenships: Array<{ id: number | string; name: string }>;
        departments: Array<{ id: number | string; name: string }>;
        positions: Array<{ id: number | string; name: string }>;
      }>(
        `/${slug.value}/create`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch user data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Create User`,
        buttonText: 'Save',
        component: SavingForm,
        componentProps: {
          suffixes: payload.suffixes,
          genders: payload.genders,
          types: payload.types,
          account_types: payload.account_types,
          civil_statuses: payload.civil_statuses,
          citizenships: payload.citizenships,
          departments: payload.departments,
          positions: payload.positions,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api
          }
        },
        size: 'xl4',
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
              router.get(window.location.pathname, {}, { preserveState: false, preserveScroll: true, replace: true });
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
          } finally {
            // Refresh current page to update datatable props
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  const getAccountsByParams = async (params: Record<string, string | number | undefined>) => {
    try {
      const response = await get(`/${slug.value}/get_accounts`, params);

      if (!response.ok) {
        throw new Error('Failed to fetch data');
      }

      const payload = response.data;

      if (!payload) return;
      // Return full paginated response: { data, meta, links } or plain array for backward compatibility
      return payload.accounts;
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  const getBranchesByParams = async (params: Record<string, string | number | undefined>) => {
    try {
      const response = await get(`/${slug.value}/get_branches`, params);

      if (!response.ok) {
        throw new Error('Failed to fetch data');
      }

      const payload = response.data;

      if (!payload) return;
      // Return full paginated response: { data, meta, links } or plain array for backward compatibility
      return payload.branches;
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  const deleteUser = async (user: User) => {
    const deleteOrRestore = user.deleted_at ? 'Restore' : 'Delete'
    const color = user.deleted_at ? 'green' : 'red';

    const buttonClass = `bg-${color}-600
      hover:bg-${color}-700
      focus:ring-${color}-500
      dark:bg-${color}-500
      dark:hover:bg-${color}-600`;

    try {
      openModal({
        modalTitle: `${deleteOrRestore} ${user?.username || ' User'}`,
        buttonText: deleteOrRestore,
        buttonClass: buttonClass,
        component: DeleteForm,
        componentProps: {
          user: user,
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
              router.get(window.location.pathname, {}, { preserveState: false, preserveScroll: true, replace: true });
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
          } finally {
            // Refresh current page to update datatable props
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  return {
    editUser,
    createUser,
    deleteUser,
    getAccountsByParams,
    getBranchesByParams,
  };
}

