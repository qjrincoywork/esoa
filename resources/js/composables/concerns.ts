import { useModal } from '@/composables/useModal';
import { usePane } from '@/composables/usePane';
import { useAjax } from '@/composables/useAjax';
import DeleteForm from '@/components/forms/concerns/DeleteForm.vue';
import SavingConcernForm from '@/components/forms/concerns/SavingConcernForm.vue';
import { ref, shallowRef, toRef, type Component, type Ref } from 'vue';
import { dispatchNotification } from '@/components/notification';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { router, usePage } from '@inertiajs/vue3';
import ConcernDetailsPaneContent from '@/components/forms/concerns/ConcernDetailsPaneContent.vue';
import { clearSoaListRowPatches, patchSoaListRow } from './soas';

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
  attachment_preview_token?: string | null
  deleted_at?: string
}

/** Client-side overlays for list rows until the next Inertia `concerns` refresh (module singleton). */
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

export function useConcerns() {
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
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        concern: Concern;
        concern_types: Array<{ value: number | string; name: string }>;
        ticket_statuses: Array<{ value: number | string; name: string }>;
      }>(
        `/${slug.value}/${concern.id}/edit`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch concern data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Edit ${concern?.title || 'Concern'}`,
        buttonText: 'Update',
        component: SavingConcernForm,
        componentProps: {
          concern: payload.concern,
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

  const viewConcern = async (concern: Concern) => {
    showLoader();
    try {
      // const payload = response.data;
      openPane({
        title: `Concern Details - ${concern.title}`,
        side: 'right',
        component: ConcernDetailsPaneContent,
        componentProps: { concern: concern },
      });
    } catch (error) {
      setPaneLoading('right', false);
      setPaneError('right', 'Error fetching data.');
      setPaneContent('right', null);
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    } finally {
      hideLoader();
    }
  };
  const previewFile = async (concern: Concern) => {
    showLoader();
    try {
      if (!concern.attachment_preview_token) {
        throw new Error('Missing preview token');
      }
      window.open(
        `/${slug.value}/preview_file?token=${encodeURIComponent(concern.attachment_preview_token)}`,
        '_blank',
        'noopener,noreferrer'
      )
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
    viewConcern,
    deleteConcern,
    previewFile,

    openPane,
    closePane,
    rightPaneVisible,
    rightPaneTitle,
    rightPaneLoading,
    rightPaneError,
    rightPaneContentComponent,
    rightPaneComponentProps,

    listRowPatches,
    patchSoaListRow,
    clearSoaListRowPatches,
  };
}
