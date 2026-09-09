import { useModal } from '@/composables/useModal';
import { useAjax } from '@/composables/useAjax';
import DeleteForm from '@/components/forms/users/DeleteForm.vue';
import SavingForm from '@/components/forms/users/SavingForm.vue';
import BulkImportForm from '@/components/forms/users/BulkImportForm.vue';
import UserRolesForm from '@/components/forms/users/UserRolesForm.vue';
import BulkUserRolesForm from '@/components/forms/users/BulkUserRolesForm.vue';
import BulkToggleActiveForm from '@/components/forms/users/BulkToggleActiveForm.vue';
import BulkDeleteForm from '@/components/forms/users/BulkDeleteForm.vue';
import VerifyForm from '@/components/forms/users/VerifyForm.vue';
import ToggleActiveForm from '@/components/forms/users/ToggleActiveForm.vue';
import UserPaneContent from '@/components/forms/users/UserPaneContent.vue';
let formApi: { getFormData: () => FormData | null } | null = null;
import { toRef } from 'vue';
import { dispatchNotification } from '@/components/notification';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { usePane } from '@/composables/usePane';
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

export interface Role {
  id?: number | string
  name?: string
  guard_name?: string
  [key: string]: any
}

/**
 * One account/branch mapping row, as `UserAccountMappingResource` shapes it.
 *
 * `key` is the composite identity the mapping UI drags and dedupes on — it is rebuilt
 * identically server-side, so unsaved rows (which have no `id` yet) still compare.
 * A blank `branch_code` covers every branch of the account.
 */
export interface UserAccountMapping {
  id?: number | null
  key: string
  account_type: string
  account_type_label?: string | null
  account_code: string
  account_name: string
  branch_code: string
  branch_name: string
}

/** The user a pane tab renders, as `UserDetailsResource` shapes it. */
export interface UserPaneDetails {
  id?: number | string
  username?: string
  email?: string
  full_name?: string | null
  type?: number | null
  type_label?: string | null
  /** Whether this user's type is scoped by account/branch mappings at all. */
  allows_account_mapping?: boolean
  /** How many mappings the type may hold; null when unlimited. */
  account_mapping_limit?: number | null
  [key: string]: any
}

/** Everything `users.account_mapping` returns — details tab, mapping tab and its options. */
export interface UserAccountMappingPayload {
  user: UserPaneDetails
  user_accounts: UserAccountMapping[]
  account_types: Array<{ value: string | number; name: string }>
}

/** The tabs the user right pane offers. */
export type UserPaneTab = 'details' | 'account_mapping';

export function useUsers() {
  const { slug } = useModulePermissions();
  const { openModal, closeModal } = useModal();
  const { get, post } = useAjax();
  const {
    openPane,
    closePane,
    setPaneLoading,
    setPaneError,
    setPaneContent,
    rightPane,
  } = usePane();

  const rightPaneVisible = toRef(rightPane, 'open');
  const rightPaneTitle = toRef(rightPane, 'title');
  const rightPaneLoading = toRef(rightPane, 'loading');
  const rightPaneError = toRef(rightPane, 'error');
  const rightPaneContentComponent = toRef(rightPane, 'contentComponent');
  const rightPaneComponentProps = toRef(rightPane, 'componentProps');

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
        all_roles: Role[];
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
          all_roles: payload.all_roles ?? [],
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
              router.get(window.location.href, {}, { preserveState: false, preserveScroll: true, replace: true });
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
        all_roles: Role[];
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
          all_roles: payload.all_roles ?? [],
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

  const getUsersWithAccounts = async (params: Record<string, string | number | undefined>) => {
    try {
      const response = await get(`/${slug.value}/account_access_users`, params);

      if (!response.ok) {
        throw new Error('Failed to fetch data');
      }

      const payload = response.data;

      if (!payload) return;
      // Paginated response: { data: [{ value, name, accounts }], current_page, last_page, ... }
      return payload.users;
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  const deleteUser = async (user: User) => {
    const isDeleted = Boolean(user.deleted_at);
    const action = isDeleted ? 'Restore' : 'Delete';
    const buttonClass = isDeleted
      ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500 dark:bg-green-500 dark:hover:bg-green-600'
      : 'bg-red-600 hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600';

    try {
      openModal({
        modalTitle: `${action} ${user.username ?? user.email ?? 'User'}`,
        buttonText: action,
        buttonClass,
        component: DeleteForm,
        componentProps: {
          user,
          isDeleted,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api;
          },
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
              router.get(window.location.href, {}, { preserveState: false, preserveScroll: true, replace: true });
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
          } finally {
            hideLoader();
          }
        },
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  const manageUserRoles = async (user: User) => {
    try {
      const response = await get<{
        user_roles: Role[]
        all_roles: Role[]
      }>(`/${slug.value}/${user.id}/edit_roles`)

      if (!response.ok) {
        throw new Error('Failed to fetch user roles')
      }

      const payload = response.data
      if (!payload) return

      openModal({
        modalTitle: `Assign Roles: ${user.username || user.id}`,
        buttonText: 'Save',
        component: UserRolesForm,
        componentProps: {
          user,
          user_roles: payload.user_roles ?? [],
          all_roles: payload.all_roles ?? [],
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api
          },
        },
        size: 'lg',
        onSubmit: async () => {
          if (!formApi) return
          const formData = formApi.getFormData()
          if (!formData) return

          showLoader()
          try {
            const response = await post(`/${slug.value}/update_roles`, formData)

            if (!response.ok) {
              dispatchNotification({
                title: 'Error',
                content: response.data.message,
                type: 'error',
              })
            } else {
              dispatchNotification({
                title: 'Success',
                content: response.data.message,
                type: 'success',
              })
              closeModal()
              router.get(window.location.href, {}, { preserveState: false, preserveScroll: true, replace: true });
            }
          } catch (err) {
            dispatchNotification({
              title: 'Error',
              content: 'Network error',
              type: 'error',
            })
            console.error(err)
          } finally {
            hideLoader()
          }
        },
      })
    } catch (error) {
      dispatchNotification({
        title: 'Error',
        content: 'Internal Server Error',
        type: 'error',
      })
      console.error('Internal Server Error:', error)
    }
  }

  const bulkManageUserRoles = async (users: User[]) => {
    if (!users.length) return

    try {
      const response = await get<{ all_roles: Role[] }>(`/${slug.value}/all_roles`)

      if (!response.ok) {
        throw new Error('Failed to fetch roles')
      }

      const payload = response.data
      if (!payload) return

      openModal({
        modalTitle: users.length === 1
          ? `Manage Roles: ${users[0]?.username || users[0]?.id}`
          : `Manage Roles for ${users.length} Users`,
        buttonText: 'Save',
        component: BulkUserRolesForm,
        componentProps: {
          users,
          all_roles: payload.all_roles ?? [],
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api
          },
        },
        size: 'lg',
        onSubmit: async () => {
          if (!formApi) return
          const formData = formApi.getFormData()
          if (!formData) return

          showLoader()
          try {
            const response = await post(`/${slug.value}/bulk_update_roles`, formData)

            if (!response.ok) {
              dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' })
            } else {
              dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' })
              closeModal()
              router.get(window.location.href, {}, { preserveState: false, preserveScroll: true, replace: true })
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' })
          } finally {
            hideLoader()
          }
        },
      })
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Internal Server Error', type: 'error' })
    }
  }

  const verifyUsers = async (users: User[]) => {
    if (!users.length) return;

    openModal({
      modalTitle: users.length === 1
        ? `Verify ${users[0]?.username || 'User'}`
        : `Verify ${users.length} Users`,
      buttonText: 'Verify',
      buttonClass: `bg-green-600 hover:bg-green-700 focus:ring-green-500
        dark:bg-green-500 dark:hover:bg-green-600`,
      component: VerifyForm,
      componentProps: {
        users,
        onReady: (api: { getFormData: () => FormData | null }) => {
          formApi = api;
        },
      },
      size: users.length > 3 ? 'md' : 'sm',
      onSubmit: async () => {
        if (!formApi) return;
        const formData = formApi.getFormData();
        if (!formData) return;

        showLoader();
        try {
          const response = await post(`/${slug.value}/verify`, formData);
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
      },
    });
  };

  const bulkVerifyCredentials = async (users: User[]) => {
    if (!users.length) return;

    openModal({
      modalTitle: users.length === 1
        ? `Send Credentials to ${users[0]?.username || 'User'}`
        : `Send Credentials to ${users.length} Users`,
      buttonText: 'Send Credentials',
      buttonClass: `bg-green-600 hover:bg-green-700 focus:ring-green-500
        dark:bg-green-500 dark:hover:bg-green-600`,
      component: VerifyForm,
      componentProps: {
        users,
        onReady: (api: { getFormData: () => FormData | null }) => {
          formApi = api;
        },
      },
      size: users.length > 3 ? 'md' : 'sm',
      onSubmit: async () => {
        // Build the payload directly so it matches the bulk endpoint contract (user_ids[]).
        const formData = new FormData();
        users.forEach((u) => formData.append('user_ids[]', String(u.id)));

        showLoader();
        try {
          const response = await post(`/${slug.value}/bulk_verify`, formData);
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
      },
    });
  };

  const bulkToggleActiveUsers = async (users: User[], newActiveValue: 0 | 1) => {
    if (!users.length) return;

    const action = newActiveValue ? 'Activate' : 'Deactivate';
    const buttonClass = newActiveValue
      ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500 dark:bg-green-500 dark:hover:bg-green-600'
      : 'bg-orange-600 hover:bg-orange-700 focus:ring-orange-500 dark:bg-orange-500 dark:hover:bg-orange-600';

    openModal({
      modalTitle: users.length === 1
        ? `${action} ${users[0]?.username || 'User'}`
        : `${action} ${users.length} Users`,
      buttonText: action,
      buttonClass,
      component: BulkToggleActiveForm,
      componentProps: {
        users,
        newActiveValue,
        onReady: (api: { getFormData: () => FormData | null }) => {
          formApi = api;
        },
      },
      size: users.length > 3 ? 'md' : 'sm',
      onSubmit: async () => {
        if (!formApi) return;
        const formData = formApi.getFormData();
        if (!formData) return;

        showLoader();
        try {
          const response = await post(`/${slug.value}/bulk_toggle_active`, formData);
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
      },
    });
  };

  const bulkDeleteUsers = async (users: User[], action: 'delete' | 'restore') => {
    if (!users.length) return;

    const isRestore = action === 'restore';
    const label = isRestore ? 'Restore' : 'Delete';
    const buttonClass = isRestore
      ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500 dark:bg-green-500 dark:hover:bg-green-600'
      : 'bg-red-600 hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600';

    openModal({
      modalTitle: users.length === 1
        ? `${label} ${users[0]?.username || 'User'}`
        : `${label} ${users.length} Users`,
      buttonText: label,
      buttonClass,
      component: BulkDeleteForm,
      componentProps: {
        users,
        action,
        onReady: (api: { getFormData: () => FormData | null }) => {
          formApi = api;
        },
      },
      size: users.length > 3 ? 'md' : 'sm',
      onSubmit: async () => {
        if (!formApi) return;
        const formData = formApi.getFormData();
        if (!formData) return;

        showLoader();
        try {
          const response = await post(`/${slug.value}/bulk_destroy`, formData);
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
      },
    });
  };

  const toggleActiveUser = async (user: User) => {
    const isCurrentlyActive = Number(user.is_active) !== 0;
    const newActiveValue: 0 | 1 = isCurrentlyActive ? 0 : 1;
    const action = isCurrentlyActive ? 'Deactivate' : 'Activate';
    const buttonClass = isCurrentlyActive
      ? 'bg-orange-600 hover:bg-orange-700 focus:ring-orange-500 dark:bg-orange-500 dark:hover:bg-orange-600'
      : 'bg-green-600 hover:bg-green-700 focus:ring-green-500 dark:bg-green-500 dark:hover:bg-green-600';

    openModal({
      modalTitle: `${action} ${user.username ?? user.email ?? 'User'}`,
      buttonText: action,
      buttonClass,
      component: ToggleActiveForm,
      componentProps: {
        user,
        newActiveValue,
        onReady: (api: { getFormData: () => FormData | null }) => {
          formApi = api;
        },
      },
      size: 'sm',
      onSubmit: async () => {
        if (!formApi) return;
        const formData = formApi.getFormData();
        if (!formData) return;

        showLoader();
        try {
          const response = await post(`/${slug.value}/toggle_active`, formData);
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
      },
    });
  };

  const bulkImportUsers = async () => {
    try {
      const response = await get<{
        columns: string[];
        required_columns: string[];
        types: { value: number | string; name: string }[];
        genders: string[];
        civil_statuses: string[];
        citizenships: string[];
        roles: string[];
      }>(`/${slug.value}/bulk_create`);

      if (!response.ok) {
        throw new Error('Failed to fetch import metadata');
      }

      const meta = response.data;
      if (!meta) return;

      type ImportApi = {
        getPayload: () => Record<string, string>[] | null;
        setResult: (result: any) => void;
      };
      let importApi: ImportApi | null = null;

      openModal({
        modalTitle: 'Bulk Import Users',
        buttonText: 'Import',
        component: BulkImportForm,
        componentProps: {
          columns: meta.columns,
          requiredColumns: meta.required_columns,
          types: meta.types,
          genders: meta.genders,
          civilStatuses: meta.civil_statuses,
          citizenships: meta.citizenships,
          roles: meta.roles,
          onReady: (api: ImportApi) => {
            importApi = api;
          },
        },
        size: 'xl4',
        onSubmit: async () => {
          const api = importApi;
          if (!api) return;

          const users = api.getPayload();
          if (!users || users.length === 0) {
            dispatchNotification({ title: 'Error', content: 'Upload a file with at least one row before importing.', type: 'error' });
            return;
          }

          showLoader();
          try {
            const res = await post(`/${slug.value}/bulk_store`, { users });

            if (!res.ok) {
              dispatchNotification({ title: 'Error', content: res.data?.message ?? 'Import failed', type: 'error' });
              return;
            }

            const result = res.data.result;
            api.setResult(result);

            if (result.failed > 0) {
              dispatchNotification({
                title: result.created > 0 ? 'Import completed with errors' : 'Import failed',
                content: res.data.message,
                type: result.created > 0 ? 'success' : 'error',
              });
              // Keep the modal open so the user can review the failed rows;
              // refresh the list underneath if any users were created.
              if (result.created > 0) {
                router.get(window.location.href, {}, { preserveState: true, preserveScroll: true, replace: true, only: [slug.value] });
              }
            } else {
              dispatchNotification({ title: 'Success', content: res.data.message, type: 'success' });
              closeModal();
              router.get(window.location.href, {}, { preserveState: false, preserveScroll: true, replace: true });
            }
          } catch (err) {
            dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
          } finally {
            hideLoader();
          }
        },
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  /**
   * Fetch a user's details and current account/branch mappings.
   *
   * One request feeds both pane tabs, so opening the pane on either costs a single
   * round trip. Returns null when the request fails; the caller decides whether that
   * is fatal — the pane falls back to the row it already has.
   */
  const getUserAccountMapping = async (userId: number | string): Promise<UserAccountMappingPayload | null> => {
    try {
      const response = await get<UserAccountMappingPayload>(`/${slug.value}/${userId}/account_mapping`);

      if (!response.ok) {
        throw new Error('Failed to fetch account mapping');
      }

      return response.data ?? null;
    } catch {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });

      return null;
    }
  };

  /**
   * Persist a user's account/branch mappings.
   *
   * The full set is posted because the endpoint treats it as the complete intended
   * state — it grants and revokes in one call — and only the three stored columns are
   * sent, since the display names are the server's to resolve. Returns the rows as
   * saved so the caller can re-seed from them instead of trusting local state.
   */
  const saveUserAccountMapping = async (
    userId: number | string,
    mappings: UserAccountMapping[],
  ): Promise<{ ok: boolean; user_accounts?: UserAccountMapping[] }> => {
    showLoader();

    try {
      const response = await post<{ message: string; user_accounts?: UserAccountMapping[] }>(
        `/${slug.value}/update_account_mapping`,
        {
          user_id: userId,
          user_accounts: mappings.map((mapping) => ({
            account_type: mapping.account_type || null,
            account_code: mapping.account_code,
            // Blank means "every branch of this account"; send it as null, not ''.
            branch_code: mapping.branch_code || null,
          })),
        },
      );

      if (!response.ok) {
        dispatchNotification({
          title: 'Error',
          content: response.data?.message ?? 'Could not save the account & branch mapping',
          type: 'error',
        });

        return { ok: false };
      }

      dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });

      return { ok: true, user_accounts: response.data.user_accounts ?? [] };
    } catch {
      dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });

      return { ok: false };
    } finally {
      hideLoader();
    }
  };

  /**
   * Open the user right pane, on the given tab.
   *
   * A row click lands on the details tab and the mapping action lands on the mapping
   * tab, but both open the same pane so a user's details and their access are never
   * two screens apart. The pane opens even when the fetch fails, showing what the list
   * row already knows rather than nothing at all.
   */
  const openUserPane = async (user: User, initialTab: UserPaneTab = 'details') => {
    showLoader();

    try {
      const payload = await getUserAccountMapping(user.id ?? '');

      openPane({
        side: 'right',
        title: `User: ${payload?.user?.username ?? user.username ?? user.email ?? user.id}`,
        component: UserPaneContent,
        componentProps: {
          user,
          details: payload?.user ?? null,
          mappings: payload?.user_accounts ?? [],
          accountTypes: payload?.account_types ?? [],
          initialTab,
        },
      });
    } catch {
      setPaneLoading('right', false);
      setPaneError('right', 'Error fetching user data.');
      setPaneContent('right', null);
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    } finally {
      hideLoader();
    }
  };

  return {
    editUser,
    createUser,
    bulkImportUsers,
    deleteUser,
    getAccountsByParams,
    getBranchesByParams,
    getUsersWithAccounts,
    getUserAccountMapping,
    saveUserAccountMapping,
    openUserPane,
    manageUserRoles,
    bulkManageUserRoles,
    bulkToggleActiveUsers,
    bulkDeleteUsers,
    verifyUsers,
    bulkVerifyCredentials,
    toggleActiveUser,
    closePane,
    rightPaneVisible,
    rightPaneTitle,
    rightPaneLoading,
    rightPaneError,
    rightPaneContentComponent,
    rightPaneComponentProps,
  };
}

