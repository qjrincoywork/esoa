import { useModal } from '@/composables/useModal';
import { useAjax } from '@/composables/useAjax';
import DeleteForm from '@/components/forms/soas/DeleteForm.vue';
import SavingForm from '@/components/forms/soas/SavingForm.vue';
import ViewForm from '@/components/forms/soas/ViewForm.vue';
let formApi: { getFormData: () => FormData | null } | null = null;
import { dispatchNotification } from '@/components/notification';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';

export interface Soa {
  id?: number
  soanum?: string
  macode?: string
  refid?: string
  upcode?: string
  billcode?: string
  billtype?: string
  billdate?: string
  upload_date?: string
  due_date?: string
  period_coverage?: string
  paid_date?: string
  amount_due?: number
  company_branch?: string
  file_pdf?: string
  file_xls?: string
  status?: string
}

export function useSoas() {
  const { slug } = useModulePermissions();
  const { openModal, closeModal } = useModal();
  const { get, post } = useAjax();

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
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
      console.error('Error fetching soa data:', error);
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
        modalTitle: `Edit ${payload.soa?.soanum || 'Soa'}`,
        buttonText: 'Update',
        component: SavingForm,
        componentProps: {
          soa: payload.soa,
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
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
      console.error('Error fetching soa data:', error);
    }
  };

  const createSoa = async () => {
    try {
      // Make AJAX request without navigation using reusable composable
      const response = await get<{
        suffixes: Array<{ id: number | string; name: string }>;
        genders: Array<{ id: number | string; name: string }>;
        civil_statuses: Array<{ id: number | string; name: string }>;
        citizenships: Array<{ id: number | string; name: string }>;
        departments: Array<{ id: number | string; name: string }>;
        positions: Array<{ id: number | string; name: string }>;
      }>(
        `/${slug.value}/create`
      );

      if (!response.ok) {
        throw new Error('Failed to fetch soa data');
      }

      const payload = response.data;

      if (!payload) return;

      openModal({
        modalTitle: `Create Soa`,
        buttonText: 'Save',
        component: SavingForm,
        componentProps: {
          suffixes: payload.suffixes,
          onReady: (api: { getFormData: () => FormData | null }) => {
            formApi = api
          }
        },
        size: 'md',
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
            hideLoader();
          }
        }
      });
    } catch (error) {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
      console.error('Error fetching user data:', error);
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
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
      console.error('Error fetching soa data:', error);
    }
  };

  return {
    editSoa,
    viewSoa,
    createSoa,
    deleteSoa,
  };
}

