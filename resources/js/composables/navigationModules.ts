import { useModal } from '@/composables/useModal';
import { useAjax } from '@/composables/useAjax';
import DeleteForm from '@/components/forms/navigation_modules/DeleteForm.vue';
import SavingForm from '@/components/forms/navigation_modules/SavingForm.vue';
import { dispatchNotification } from '@/components/notification';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { router } from '@inertiajs/vue3';

let formApi: { getFormData: () => FormData | null } | null = null;

export interface NavigationModule {
    id?: number | string;
    name?: string;
    slug?: string;
    url?: string;
    icon?: string;
    navigation_id?: number | null;
    permission_id?: number | null;
    color?: string;
    ref_id?: number | null;
    order_number?: number | null;
    status?: number;
    deleted_at?: string | null;
    navigation?: { id: number; name: string };
    permission?: { id: number; name: string };
    [key: string]: any;
}

type SelectOption   = { id: number; name: string };
type StatusOption   = { value: number; name: string };
type ParentModule   = SelectOption & { navigation_id: number; navigation?: SelectOption };

function refreshPage() {
    router.get(window.location.pathname, {}, { preserveState: false, preserveScroll: true, replace: true });
}

export interface UseNavigationModulesOptions {
    /**
     * Called instead of a full-page reload after a successful create/update/delete.
     * Lets an embedded list (e.g. the navigation details pane's Modules tab) refresh
     * itself in place rather than reloading the whole host page.
     */
    onMutated?: () => void;
}

export function useNavigationModules(options: UseNavigationModulesOptions = {}) {
    // Explicit slug: this composable is also used from within the navigations details
    // pane, where the host page's component name is "navigations/Index", not
    // "navigation_modules/Index" — auto-derivation would point requests at the wrong module.
    const { slug } = useModulePermissions({ slug: 'navigation_modules' });
    const { openModal, closeModal } = useModal();
    const { get, post } = useAjax();

    const afterMutation = () => {
        if (options.onMutated) {
            options.onMutated();
        } else {
            refreshPage();
        }
    };

    const createNavigationModule = async (defaultNavigationId?: number, defaultRefId?: number) => {
        try {
            const response = await get<{
                navigations:    SelectOption[];
                permissions:    SelectOption[];
                parent_modules: ParentModule[];
                statuses:       StatusOption[];
            }>(`/${slug.value}/create`);

            if (!response.ok) throw new Error('Failed to fetch form data');

            const payload = response.data;
            if (!payload) return;

            openModal({
                modalTitle: 'Create Navigation Module',
                buttonText: 'Save',
                component: SavingForm,
                componentProps: {
                    navigationModule: (defaultNavigationId || defaultRefId)
                        ? { navigation_id: defaultNavigationId, ref_id: defaultRefId }
                        : undefined,
                    navigations:   payload.navigations,
                    permissions:   payload.permissions,
                    parentModules: payload.parent_modules,
                    statuses:      payload.statuses,
                    onReady: (api: { getFormData: () => FormData | null }) => { formApi = api; },
                },
                size: 'xl2',
                onSubmit: async () => {
                    if (!formApi) return;
                    const formData = formApi.getFormData();
                    if (!formData) return;

                    showLoader();
                    try {
                        const res = await post(`/${slug.value}/store`, formData);
                        if (!res.ok) {
                            dispatchNotification({ title: 'Error', content: res.data.message, type: 'error' });
                        } else {
                            dispatchNotification({ title: 'Success', content: res.data.message, type: 'success' });
                            closeModal();
                            afterMutation();
                        }
                    } catch {
                        dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
                    } finally {
                        hideLoader();
                    }
                },
            });
        } catch {
            dispatchNotification({ title: 'Error', content: 'Error fetching form data', type: 'error' });
        }
    };

    const editNavigationModule = async (module: NavigationModule) => {
        try {
            const response = await get<{
                navigation_module: NavigationModule;
                navigations:       SelectOption[];
                permissions:       SelectOption[];
                parent_modules:    ParentModule[];
                statuses:          StatusOption[];
            }>(`/${slug.value}/${module.id}/edit`);

            if (!response.ok) throw new Error('Failed to fetch module data');

            const payload = response.data;
            if (!payload) return;

            openModal({
                modalTitle: `Edit ${payload.navigation_module?.name || 'Module'}`,
                buttonText: 'Update',
                component: SavingForm,
                componentProps: {
                    navigationModule: payload.navigation_module,
                    navigations:      payload.navigations,
                    permissions:      payload.permissions,
                    parentModules:    payload.parent_modules,
                    statuses:         payload.statuses,
                    onReady: (api: { getFormData: () => FormData | null }) => { formApi = api; },
                },
                size: 'xl2',
                onSubmit: async () => {
                    if (!formApi) return;
                    const formData = formApi.getFormData();
                    if (!formData) return;

                    showLoader();
                    try {
                        const res = await post(`/${slug.value}/update`, formData);
                        if (!res.ok) {
                            dispatchNotification({ title: 'Error', content: res.data.message, type: 'error' });
                        } else {
                            dispatchNotification({ title: 'Success', content: res.data.message, type: 'success' });
                            closeModal();
                            afterMutation();
                        }
                    } catch {
                        dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
                    } finally {
                        hideLoader();
                    }
                },
            });
        } catch {
            dispatchNotification({ title: 'Error', content: 'Error fetching module data', type: 'error' });
        }
    };

    const deleteNavigationModule = async (module: NavigationModule) => {
        const isRestore   = Boolean(module.deleted_at);
        const action      = isRestore ? 'Restore' : 'Delete';
        const color       = isRestore ? 'green' : 'red';
        const buttonClass = `bg-${color}-600 hover:bg-${color}-700 focus:ring-${color}-500
            dark:bg-${color}-500 dark:hover:bg-${color}-600`;

        openModal({
            modalTitle:  `${action} ${module.name || 'Module'}`,
            buttonText:  action,
            buttonClass,
            component:   DeleteForm,
            componentProps: {
                navigationModule: module,
                onReady: (api: { getFormData: () => FormData | null }) => { formApi = api; },
            },
            size: 'sm',
            onSubmit: async () => {
                if (!formApi) return;
                const formData = formApi.getFormData();
                if (!formData) return;

                showLoader();
                try {
                    const res = await post(`/${slug.value}/destroy`, formData);
                    if (!res.ok) {
                        dispatchNotification({ title: 'Error', content: res.data.message, type: 'error' });
                    } else {
                        dispatchNotification({ title: 'Success', content: res.data.message, type: 'success' });
                        closeModal();
                        afterMutation();
                    }
                } catch {
                    dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });
                } finally {
                    hideLoader();
                }
            },
        });
    };

    return { createNavigationModule, editNavigationModule, deleteNavigationModule };
}
