import { useModal } from '@/composables/useModal';
import { useAjax } from '@/composables/useAjax';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SavingForm from '@/components/forms/users/SavingForm.vue';
let formApi: { getFormData: () => FormData | null } | null = null;

export interface User {
    id?: number | string;
    username?: string;
    email?: string;
    [key: string]: any;
}

export interface Suffixes {
    id?: number | string;
    name?: string;
    [key: string]: any;
}

export function useUsers() {
    const { openModal } = useModal();
    const { get } = useAjax();

    const editUser = async (user: User) => {
        try {
            // Make AJAX request without navigation using reusable composable
            const response = await get<{
                user: User;
                suffixes: Array<{ id: number | string; name: string }>;
                genders: Array<{ id: number | string; name: string }>;
                civil_statuses: Array<{ id: number | string; name: string }>;
                citizenships: Array<{ id: number | string; name: string }>;
                departments: Array<{ id: number | string; name: string }>;
                positions: Array<{ id: number | string; name: string }>;
            }>(
                `/users/${user.id}/edit`
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
                    civil_statuses: payload.civil_statuses,
                    citizenships: payload.citizenships,
                    departments: payload.departments,
                    positions: payload.positions,
                    onReady: (api: { getFormData: () => FormData | null }) => {
                        formApi = api
                    }
                },
                size: 'lg',
                onSubmit: () => {
                    if (!formApi) return;

                    const formData = formApi.getFormData();
                    if (!formData) return;

                    router.post(`/users/update`, formData, {
                        preserveScroll: true,
                    });
                }
            });
        } catch (error) {
            console.error('Error fetching user data:', error);
        }
    };

    const createUser = () => {
        openModal({
            modalTitle: 'Create New User',
            buttonText: 'Create',
            component: SavingForm,
            submitUrl: '/users',
            submitMethod: 'post',
            submitData: {},
            size: 'lg',
        });
    };

    return {
        editUser,
        createUser,
    };
}

