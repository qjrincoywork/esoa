import { useModal } from '@/composables/useModal';
import { useAjax } from '@/composables/useAjax';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import DeleteForm from '@/components/forms/users/DeleteForm.vue';
import SavingForm from '@/components/forms/users/SavingForm.vue';
let formApi: { getFormData: () => FormData | null } | null = null;
import { dispatchNotification } from '@/components/notification';

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
    const { openModal, closeModal } = useModal();
    const { get, post } = useAjax();

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
                onSubmit: async () => {
                    if (!formApi) return;

                    const formData = formApi.getFormData();
                    if (!formData) return;

                    const response = await post(`/users/update`, formData);

                    if (!response.ok) {
                        // console.log('onSubmit Failed:', response, response.status); 
                        //To be Updated the showing of validation errors in the form
                        dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' });
                    } else {
                        dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });
                        closeModal();
                    }
                }
            });
        } catch (error) {
            console.error('Error fetching user data:', error);
        }
    };

    const createUser = async () => {
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
                `/users/create`
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
                    civil_statuses: payload.civil_statuses,
                    citizenships: payload.citizenships,
                    departments: payload.departments,
                    positions: payload.positions,
                    onReady: (api: { getFormData: () => FormData | null }) => {
                        formApi = api
                    }
                },
                size: 'md',
                onSubmit: async () => {
                    if (!formApi) return;

                    const formData = formApi.getFormData();
                    if (!formData) return;

                    const response = await post(`/users/store`, formData);

                    if (!response.ok) {
                        // console.log('onSubmit Failed:', response, response.status); 
                        //To be Updated the showing of validation errors in the form
                        dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' });
                    } else {
                        dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });
                        closeModal();
                    }
                }
            });
        } catch (error) {
            console.error('Error fetching user data:', error);
        }
        // openModal({
        //     modalTitle: 'Create New User',
        //     buttonText: 'Create',
        //     component: SavingForm,
        //     submitUrl: '/users',
        //     // submitMethod: 'post',
        //     submitData: {},
        //     size: 'lg',
        // });
    };

    const deleteUser = async (user: User) => {
        try {
            openModal({
                modalTitle: `Delete ${user?.username || ' User'}`,
                buttonText: 'Delete',
                buttonClass: 'bg-red-600 hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600',
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

                    const response = await post(`/users/destroy`, formData);

                    if (!response.ok) {
                        dispatchNotification({ title: 'Error', content: response.data.message, type: 'error' });
                    } else {
                        dispatchNotification({ title: 'Success', content: response.data.message, type: 'success' });
                        closeModal();
                    }
                }
            });
        } catch (error) {
            console.error('Error fetching user data:', error);
        }
    };

    return {
        editUser,
        createUser,
        deleteUser,
    };
}

