import { useModal } from '@/composables/useModal';
import { usePane } from '@/composables/usePane';
import { useAjax } from '@/composables/useAjax';
import DeleteForm from '@/components/forms/soas/DeleteForm.vue';
import SavingSoaForm from '@/components/forms/soas/SavingSoaForm.vue';
import AccountBranchUpdateForm from '@/components/forms/soas/AccountBranchUpdateForm.vue';
import ViewForm from '@/components/forms/soas/ViewForm.vue';
import UntagForm from '@/components/forms/soas/UntagForm.vue';
import ManageFileForm from '@/components/forms/soas/ManageFileForm.vue';
import { ref, shallowRef, toRef, type Component, type Ref } from 'vue';
import { Soa } from '@/types';
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

export function useSoas() {
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
  const authUser = (page.props as { auth?: { user?: { id?: number; user_detail?: unknown } } }).auth?.user;

  const rightPaneVisible = toRef(rightPane, 'open');
  const rightPaneTitle = toRef(rightPane, 'title');
  const rightPaneLoading = toRef(rightPane, 'loading');
  const rightPaneError = toRef(rightPane, 'error');
  const rightPaneContentComponent = toRef(rightPane, 'contentComponent');
  const rightPaneComponentProps = toRef(rightPane, 'componentProps');

  const topPaneVisible = toRef(topPane, 'open');
  const topPaneTitle = toRef(topPane, 'title');
  const topPaneLoading = toRef(topPane, 'loading');
  const topPaneError = toRef(topPane, 'error');
  const topPaneContentComponent = toRef(topPane, 'contentComponent');
  const topPaneComponentProps = toRef(topPane, 'componentProps');

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
    showLoader();
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
      const isAccountBranchAdmin = authUser?.user_detail?.employee_no == '' || authUser?.user_detail?.employee_no == null;
      const formComponent = isAccountBranchAdmin ? AccountBranchUpdateForm : SavingSoaForm;
      const formComponentProps = isAccountBranchAdmin
        ? {
            soa: payload.soa,
            status_types: payload.status_types ?? [],
            user: authUser ?? undefined,
            onReady: (api: { getFormData: () => FormData | null }) => {
              formApi = api
            }
          }
        : {
          soa: payload.soa,
          account_types: payload.account_types,
          bill_types: payload.bill_types ?? [],
          status_types: payload.status_types ?? [],
          billing_ref_from_types: payload.billing_ref_from_types ?? [],
          user: authUser ?? undefined,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api
          }
        };

      openModal({
        modalTitle: `Edit ${payload.soa?.soa_number || 'Soa'}`,
        buttonText: 'Update',
        component: formComponent,
        componentProps: formComponentProps,
        size: 'xl',
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

  const newSoa = async () => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        account_types: Array<{ value: number | string; name: string }>;
        bill_types: Array<{ value: number | string; name: string }>;
        status_types: Array<{ value: number | string; name: string }>;
        billing_ref_from_types: Array<{ value: number | string; name: string }>;
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
          billing_ref_from_types: payload.billing_ref_from_types ?? [],
          user: authUser ?? undefined,
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

  const fileList = async (soa: Soa, item: any) => {
    showLoader();
    try {
      const params = {
        soa_id: soa.id,
        billing_ref: soa.billing_ref,
        claimnum: item.claimnum,
        policynum: item.policynum,
        billing_ref_from: soa.billing_ref_from,
      };
      const response = await get(`/${slug.value}/file_list`, params);

      if (!response.ok) {
        throw new Error('Failed to fetch data');
      }

      const payload = response.data;
      openPane({
        title: `Records Management Attachments`,
        side: 'top',
        component: SoaFileBrowser,
        componentProps: { soa: soa, files: payload.files ?? [] },
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    } finally {
      hideLoader();
    }
  };

  const billingAttachments = (soa: Soa) => {
    if (soa?.id == null) {
      dispatchNotification({ title: 'Error', content: 'Invalid SOA', type: 'error' });
      return;
    }
    const path = `/${slug.value}/${soa.id}/attachment/pdf`;
    const url = new URL(path, window.location.origin).href;
    const opened = window.open(url, '_blank', 'noopener,noreferrer');
    if (!opened) {
      dispatchNotification({
        title: 'Error',
        content: 'Could not open a new tab.',
        type: 'error',
      });
    }
  };

  // Open a SOA-related right pane (drawer) showing file list.
  // This is intentionally implemented here so `soas.ts` owns the data-fetch + pane state.
  const openSoaFilesPane = async (soa: Soa) => {
    const soaLabel = (soa as any)?.soanum ?? (soa as any)?.soa_number ?? '';

    try {
      // Record "viewed" activity for account_branch_admin users only (best-effort).
      // Backend enforces role + de-duplication (one per SOA).
      const isAccountBranchAdmin = authUser?.user_detail?.employee_no == '' || authUser?.user_detail?.employee_no == null;
      if (isAccountBranchAdmin && soa?.id != null) {
        void post(`/${slug.value}/${soa.id}/record_viewed`, {});
      }

      openPane({
        side: 'right',
        title: `${soaLabel ? 'Billing Invoice: ' + soaLabel : 'Details'}`,
        component: SoaDetailsPaneContent,
        componentProps: {
          soa: soa,
        },
      });
    } catch (error) {
      setPaneLoading('right', false);
      setPaneError('right', 'Error fetching SOA files.');
      setPaneContent('right', null);
      dispatchNotification({
        title: 'Error',
        content: 'Error fetching SOA files',
        type: 'error',
      });
    }
  };

  const getAccountsByParams = async (params: Record<string, string | number | undefined>) => {
    showLoader();
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
    } finally {
      hideLoader();
    }
  };

  const getBillingRefsByParams = async (params: Record<string, string | number | undefined>) => {
    showLoader();
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
    } finally {
      hideLoader();
    }
  };

  const getBranchesByParams = async (params: Record<string, string | number | undefined>) => {
    showLoader();
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
    } finally {
      hideLoader();
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

  const exportBillingInvoices = async (params: Record<string, string | number>) => {
    showLoader();
    try {
      const qs = new URLSearchParams();
      Object.entries(params).forEach(([key, value]) => {
        if (value !== '' && value !== undefined && value !== null) {
          qs.set(key, String(value));
        }
      });

      const response = await fetch(`/${slug.value}/export?${qs.toString()}`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/vnd.ms-excel, application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      const contentType = response.headers.get('Content-Type') ?? '';

      if (!response.ok) {
        let message = 'Export failed.';
        if (contentType.includes('application/json')) {
          const body = (await response.json()) as { message?: string };
          message = body?.message ?? message;
        }
        throw new Error(message);
      }

      const blob = await response.blob();
      const disposition = response.headers.get('Content-Disposition') ?? '';
      const match = disposition.match(/filename[^;=\n]*=["']?([^"';\n]+)["']?/i);
      const filename =
        match?.[1]?.trim() ??
        `billing_invoices_${new Date().toISOString().slice(0, 10)}.xls`;

      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);

      dispatchNotification({
        title: 'Success',
        content: 'Billing invoices export downloaded.',
        type: 'success',
      });
    } catch (error) {
      dispatchNotification({
        title: 'Error',
        content: error instanceof Error ? error.message : 'Export failed.',
        type: 'error',
      });
    } finally {
      hideLoader();
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
    fileList,
    billingAttachments,
    openSoaFilesPane,
    newSoa,
    deleteSoa,
    manageFile,
    untagSoa,
    recomputeTax,
    getAccountsByParams,
    getBranchesByParams,
    getBillingRefsByParams,
    exportBillingInvoices,
    adjustSoaAmount,

    openPane,
    closePane,
    topPaneVisible,
    topPaneTitle,
    topPaneLoading,
    topPaneError,
    topPaneContentComponent,
    topPaneComponentProps,
    rightPaneVisible,
    rightPaneTitle,
    rightPaneLoading,
    rightPaneError,
    rightPaneContentComponent,
    rightPaneComponentProps,

    soaListRowPatches,
    patchSoaListRow,
    clearSoaListRowPatches,
  };
}

