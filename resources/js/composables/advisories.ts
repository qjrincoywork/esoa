import { useModal } from '@/composables/useModal';
import { useAjax } from '@/composables/useAjax';
import { dispatchNotification } from '@/components/notification';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { router } from '@inertiajs/vue3';
import SavingAdvisoryForm from '@/components/forms/advisories/SavingAdvisoryForm.vue';
import ToggleActiveForm from '@/components/forms/advisories/ToggleActiveForm.vue';
import DeleteForm from '@/components/forms/advisories/DeleteForm.vue';
import AdvisoryContent from '@/components/advisories/AdvisoryContent.vue';

let formApi: { getFormData: () => FormData | null } | null = null;

/** An advisory as the admin listing and form see it (App\Http\Resources\AdvisoryResource). */
export interface Advisory {
  id?: number
  title?: string
  message?: string | null
  image?: string | null
  image_url?: string | null
  is_active?: number
  starts_at?: string | null
  ends_at?: string | null
  starts_at_value?: string | null
  ends_at_value?: string | null
  state?: string
  state_value?: number
  state_color?: string
  created_by?: string | null
  updated_by?: string | null
  created_at?: string | null
  updated_at?: string | null
  deleted_at?: string | null
}

/** One advisory as the sign-in pop-up sees it (App\Support\LoginAdvisories::present). */
export interface LoginAdvisory {
  id: number
  title: string
  message: string | null
  image_url: string | null
}

/** The `login_advisories` shared prop: present on the first page after signing in only. */
export interface LoginAdvisories {
  key: string
  items: LoginAdvisory[]
}

const SEEN_STORAGE_KEY = 'login_advisories.seen';

/**
 * Sign-ins whose advisories were already shown. The server sends them with one page
 * only, but browser back/forward replays that page's props from history — remembering
 * the key keeps the pop-up from coming back each time. Kept in memory first, in
 * sessionStorage as well so it also holds across a reload; storage may be unavailable,
 * and then memory alone is enough for the common case.
 */
const seenKeys = new Set<string>();

export function hasSeenLoginAdvisories(key: string): boolean {
  if (seenKeys.has(key)) return true;
  try {
    return window.sessionStorage.getItem(SEEN_STORAGE_KEY) === key;
  } catch {
    return false;
  }
}

export function markLoginAdvisoriesSeen(key: string): void {
  seenKeys.add(key);
  try {
    window.sessionStorage.setItem(SEEN_STORAGE_KEY, key);
  } catch {
    // Storage blocked: the in-memory set still covers this tab.
  }
}

export function useAdvisories() {
  const { slug } = useModulePermissions();
  const { openModal, closeModal } = useModal();
  const { get, post } = useAjax();

  /** Reload the listing with fresh rows, keeping the URL's filters and page. */
  const reloadList = () => {
    router.get(window.location.href, {}, { preserveState: false, preserveScroll: true, replace: true });
  };

  /**
   * Send the form open in the modal to `endpoint`, then close and reload on success.
   * The one submit path every advisory modal shares.
   */
  const submitTo = (endpoint: string) => async () => {
    if (!formApi) return;
    const formData = formApi.getFormData();
    if (!formData) return;

    showLoader();
    try {
      const response = await post(`/${slug.value}/${endpoint}`, formData);

      if (!response.ok) {
        dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' });
      } else {
        dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });
        closeModal();
        reloadList();
      }
    } catch {
      dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
    } finally {
      hideLoader();
    }
  };

  const onReady = (api: { getFormData: () => FormData | null }) => {
    formApi = api;
  };

  const newAdvisory = () => {
    openModal({
      modalTitle: 'New Advisory',
      buttonText: 'Save',
      component: SavingAdvisoryForm,
      componentProps: { onReady },
      size: 'lg',
      onSubmit: submitTo('store'),
    });
  };

  const editAdvisory = async (advisory: Advisory) => {
    showLoader();
    try {
      const response = await get<{ advisory: Advisory }>(`/${slug.value}/${advisory.id}/edit`);

      if (!response.ok || !response.data?.advisory) {
        throw new Error('Failed to fetch advisory data');
      }

      openModal({
        modalTitle: `Edit ${advisory.title || 'Advisory'}`,
        buttonText: 'Update',
        component: SavingAdvisoryForm,
        componentProps: { advisory: response.data.advisory, onReady },
        size: 'lg',
        onSubmit: submitTo('update'),
      });
    } catch {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    } finally {
      hideLoader();
    }
  };

  const toggleAdvisory = (advisory: Advisory) => {
    const isEnabled = Number(advisory.is_active) !== 0;
    const action = isEnabled ? 'Disable' : 'Enable';
    const buttonClass = isEnabled
      ? 'bg-orange-600 hover:bg-orange-700 focus:ring-orange-500 dark:bg-orange-500 dark:hover:bg-orange-600'
      : 'bg-green-600 hover:bg-green-700 focus:ring-green-500 dark:bg-green-500 dark:hover:bg-green-600';

    openModal({
      modalTitle: `${action} ${advisory.title || 'Advisory'}`,
      buttonText: action,
      buttonClass,
      component: ToggleActiveForm,
      componentProps: { advisory, newActiveValue: isEnabled ? 0 : 1, onReady },
      size: 'sm',
      onSubmit: submitTo('toggle_active'),
    });
  };

  const deleteAdvisory = (advisory: Advisory) => {
    const deleteOrRestore = advisory.deleted_at ? 'Restore' : 'Delete';
    const buttonClass = advisory.deleted_at
      ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500 dark:bg-green-500 dark:hover:bg-green-600'
      : 'bg-red-600 hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600';

    openModal({
      modalTitle: `${deleteOrRestore} ${advisory.title || 'Advisory'}`,
      buttonText: deleteOrRestore,
      buttonClass,
      component: DeleteForm,
      componentProps: { advisory, onReady },
      size: 'sm',
      onSubmit: submitTo('destroy'),
    });
  };

  /** Show the advisory exactly as users will see it after signing in. */
  const previewAdvisory = (advisory: Advisory) => {
    openModal({
      modalTitle: advisory.title || 'Advisory',
      component: AdvisoryContent,
      componentProps: {
        title: advisory.title ?? '',
        message: advisory.message ?? null,
        imageUrl: advisory.image_url ?? null,
      },
      size: 'xl',
      hasSubmitButton: false,
      closeOnClickOutside: true,
    });
  };

  return {
    newAdvisory,
    editAdvisory,
    toggleAdvisory,
    deleteAdvisory,
    previewAdvisory,
  };
}
