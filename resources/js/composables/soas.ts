import { useModal } from '@/composables/useModal';
import { useAjax } from '@/composables/useAjax';
import DeleteForm from '@/components/forms/soas/DeleteForm.vue';
import SavingForm from '@/components/forms/soas/SavingForm.vue';
import SavingSoaForm from '@/components/forms/soas/SavingSoaForm.vue';
import ViewForm from '@/components/forms/soas/ViewForm.vue';
import UntagForm from '@/components/forms/soas/UntagForm.vue';
import ManageFileForm from '@/components/forms/soas/ManageFileForm.vue';
import { ref, shallowRef, type Component, type Ref } from 'vue';
let formApi: { getFormData: () => FormData | null } | null = null;

/** Client-side overlays for list rows until the next Inertia `soas` refresh (module singleton). */
const soaListRowPatches: Ref<Record<number, Record<string, unknown>>> = ref({});

export function patchSoaListRow(id: number, patch: Record<string, unknown>): void {
  if (typeof id !== 'number' || !Number.isFinite(id)) return;
  const key = id;
  const prev = soaListRowPatches.value[key] ?? {};
  soaListRowPatches.value = {
    ...soaListRowPatches.value,
    [key]: { ...prev, ...patch },
  };
}

export function clearSoaListRowPatches(): void {
  soaListRowPatches.value = {};
}
import { dispatchNotification } from '@/components/notification';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { router, usePage } from '@inertiajs/vue3';
import SoaFileBrowser from '@/components/forms/soas/SoaFileBrowser.vue';
import SoaDetailsPaneContent from '@/components/forms/soas/SoaDetailsPaneContent.vue';

export interface Soa {
  id?: number
  user_id?: number
  soa_number?: string
  account_code?: string
  branch_code?: string
  billing_ref?: string
  bill_type?: number
  status?: number
  bill_date?: string
  due_date?: string
  period_date_from?: string
  period_date_to?: string
  amount?: string | number
  /** Numeric amount from API (e.g. SoaResource); preferred for math when present */
  amount_raw?: number
  amount_paid?: number
  payment_adjustment?: number
  balance?: number
  file_pdf?: string
  file_xls?: string
}

export function useSoas() {
  const page = usePage();
  const { slug } = useModulePermissions();
  const { openModal, closeModal } = useModal();
  const { get, post } = useAjax();
  const authUser = (page.props as { auth?: { user?: { id?: number; user_detail?: unknown } } }).auth?.user;

  // Right pane (drawer) state for dynamic content.
  const rightPaneVisible = ref(false);
  const rightPaneTitle = ref('');
  const rightPaneLoading = ref(false);
  const rightPaneError = ref<string | null>(null);
  const rightPaneContentComponent = shallowRef<Component | null>(null);
  const rightPaneComponentProps = ref<Record<string, any>>({});

  const closeRightPane = () => {
    rightPaneVisible.value = false;
    rightPaneLoading.value = false;
    rightPaneTitle.value = '';
    rightPaneError.value = null;
    rightPaneContentComponent.value = null;
    rightPaneComponentProps.value = {};
  };

  const openRightPane = (options: {
    title: string;
    component: Component | null;
    componentProps?: Record<string, any>;
  }) => {
    rightPaneTitle.value = options.title;
    rightPaneContentComponent.value = options.component;
    rightPaneComponentProps.value = options.componentProps ?? {};
    rightPaneLoading.value = false;
    rightPaneError.value = null;
    rightPaneVisible.value = true;
  };

  const untagSoa = async (soa: Soa) => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        soa: Soa;
        untag_types: Array<{ value: number | string; name: string }>;
      }>(
        `/${slug.value}/${soa.id}/untag`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch soa data');
      }

      const payload = response.data;

      if (!payload) return;
      openModal({
        modalTitle: `Undo SOA ${soa?.soanum || ' as  Paid'}`,
        buttonText: 'Undo/Untag',
        component: UntagForm,
        componentProps: {
          soa: soa,
          untag_types: payload.untag_types,
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
            const response = await post(`/${slug.value}/update_tag`, formData);

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
          } finally {
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  const viewSoa = async (soa: Soa) => {
    try {
      openModal({
        modalTitle: `View ${soa?.soanum || 'Soa'}`,
        buttonText: 'View',
        component: ViewForm,
        componentProps: {
          soa: soa,
        },
        size: 'lg',
        hasSubmitButton: false,
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  const manageFile = async (soa: Soa) => {
    try {
      openModal({
        modalTitle: `Manage ${soa?.soanum || ' Files'}`,
        buttonText: 'Submit',
        component: ManageFileForm,
        componentProps: {
          soa: soa,
        },
        size: 'lg'
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  const recomputeTax = async (formData: FormData) => {
    showLoader();
    try {
      const response = await post(`/${slug.value}/recompute_tax`, formData);

      if (!response.ok) {
        //To be Updated the showing of validation errors in the form
        dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' });
      } else {
        dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });
        closeModal();
      }
    } catch (err) {
      dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
    } finally {
      hideLoader();
    }
  };

  const editSoa = async (soa: Soa) => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        soa: Soa;
      }>(
        `/${slug.value}/${soa.id}/edit`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch soa data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Edit ${payload.soa?.soa_number || 'Soa'}`,
        buttonText: 'Update',
        component: SavingSoaForm,
        componentProps: {
          account_types: payload.account_types,
          bill_types: payload.bill_types ?? [],
          status_types: payload.status_types ?? [],
          user: authUser ?? undefined,
          soa: payload.soa,
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
          } finally {
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  const createSoa = async () => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        account_types: Array<{ value: number | string; name: string }>;
      }>(
        `/${slug.value}/create`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Create Soa`,
        buttonText: 'Save',
        component: SavingForm,
        componentProps: {
          account_types: payload.account_types,
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
              // Refresh current page to update datatable props
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

  const newSoa = async () => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        account_types: Array<{ value: number | string; name: string }>;
        bill_types: Array<{ value: number | string; name: string }>;
        status_types: Array<{ value: number | string; name: string }>;
      }>(
        `/${slug.value}/create`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Create Soa`,
        buttonText: 'Save',
        component: SavingSoaForm,
        componentProps: {
          account_types: payload.account_types,
          bill_types: payload.bill_types ?? [],
          status_types: payload.status_types ?? [],
          user: authUser ?? undefined,
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
              // Refresh current page to update datatable props
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

  const fileList = async (soa: Soa) => {
    try {
      const response = await get(`/${slug.value}/file_list`, soa);

      if (!response.ok) {
        throw new Error('Failed to fetch data');
      }

      const payload = response.data;

      openModal({
        modalTitle: `View ${soa?.soa_number + ' Files'}`,
        buttonText: 'View',
        component: SoaFileBrowser,
        componentProps: {
          soa: soa,
          files: payload.files,
        },
        size: 'lg',
        hasSubmitButton: false,
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  // Open a SOA-related right pane (drawer) showing file list.
  // This is intentionally implemented here so `soas.ts` owns the data-fetch + pane state.
  const openSoaFilesPane = async (soa: Soa) => {
    // Note: `soa` might be undefined if the event payload is missing.
    // Use optional chaining for all reads to prevent runtime crashes.
    const soaLabel = (soa as any)?.soanum ?? (soa as any)?.soa_number ?? '';

    // Open immediately so the user gets instant feedback.
    rightPaneLoading.value = true;
    rightPaneTitle.value = `${soaLabel ? 'Billing Invoice: ' + soaLabel : 'Details'}`;
    rightPaneVisible.value = true;
    rightPaneError.value = null;
    rightPaneContentComponent.value = null;
    rightPaneComponentProps.value = {};

    try {
      openRightPane({
        title: `${soaLabel ? 'Billing Invoice: ' + soaLabel : 'Details'}`,
        component: SoaDetailsPaneContent,
        componentProps: {
          soa: soa,
        },
      });
    } catch (error) {
      // Keep the pane open so we can visually confirm row-click + endpoint issues.
      rightPaneLoading.value = false;
      rightPaneError.value = 'Error fetching SOA files.';
      rightPaneContentComponent.value = null;
      dispatchNotification({
        title: 'Error',
        content: 'Error fetching SOA files',
        type: 'error',
      });
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

  const getBillingRefsByParams = async (params: Record<string, string | number | undefined>) => {
    try {
      const response = await get(`/${slug.value}/get_billing_refs`, params);

      if (!response.ok) {
        throw new Error('Failed to fetch data');
      }

      const payload = response.data;

      if (!payload) return;
      // Return full paginated response: { data, meta, links } or plain array for backward compatibility
      return payload.billing_refs;
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

  const deleteSoa = async (soa: Soa) => {
    const deleteOrRestore = soa.deleted_at ? 'Restore' : 'Delete'
    const color = soa.deleted_at ? 'green' : 'red';

    const buttonClass = `bg-${color}-600
      hover:bg-${color}-700
      focus:ring-${color}-500
      dark:bg-${color}-500
      dark:hover:bg-${color}-600`;

    try {
      openModal({
        modalTitle: `${deleteOrRestore} ${soa?.name || ' Soa'}`,
        buttonText: deleteOrRestore,
        buttonClass: buttonClass,
        component: DeleteForm,
        componentProps: {
          soa: soa,
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
          } finally {
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    }
  };

  const adjustSoaAmount = async (payload: { soa_id: number; operation: 'add' | 'deduct'; amount: number }) => {
    const formData = new FormData();
    formData.append('soa_id', String(payload.soa_id));
    formData.append('operation', payload.operation);
    formData.append('amount', String(payload.amount));

    showLoader();
    try {
      const response = await post(`/${slug.value}/adjust_amount`, formData);

      if (!response.ok) {
        dispatchNotification({
          title: 'Error',
          content: (response.data as { message?: string })?.message ?? 'Update failed.',
          type: 'error',
        });
      } else {
        dispatchNotification({
          title: 'Success',
          content: (response.data as { message?: string })?.message ?? 'Amount updated.',
          type: 'success',
        });
        const data = response.data as { amount?: string; amount_raw?: number };
        if (data.amount != null && data.amount_raw != null) {
          patchSoaListRow(payload.soa_id, { amount: data.amount, amount_raw: data.amount_raw });
        }
      }

      return response;
    } catch {
      dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
      return null;
    } finally {
      hideLoader();
    }
  };

  return {
    editSoa,
    viewSoa,
    createSoa,
    fileList,
    openSoaFilesPane,
    newSoa,
    deleteSoa,
    manageFile,
    untagSoa,
    recomputeTax,
    getAccountsByParams,
    getBranchesByParams,
    getBillingRefsByParams,
    adjustSoaAmount,

    // Right pane API/state
    rightPaneVisible,
    rightPaneTitle,
    rightPaneLoading,
    rightPaneError,
    rightPaneContentComponent,
    rightPaneComponentProps,
    closeRightPane,

    soaListRowPatches,
    patchSoaListRow,
    clearSoaListRowPatches,
  };
}

