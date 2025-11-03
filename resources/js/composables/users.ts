import { useModal } from '@/composables/useModal';
import { router, usePage } from '@inertiajs/vue3';
import SavingForm from '@/components/forms/users/SavingForm.vue';

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

    const editUser = (user: User) => {
        router.get(`/users/${user.id}/edit`, {}, {
            preserveState: true,
            replace: true,
            only: ['edit_user_payload'],
            onSuccess: () => {
                const page = usePage();
                const payload = (page.props as any).edit_user_payload as { user: User; suffixes: Array<{ id: number | string; name: string }>; } | undefined;
                if (!payload) return;

                openModal({
                    modalTitle: `Edit ${payload.user?.username || 'User'}`,
                    buttonText: 'Update',
                    component: SavingForm,
                    componentProps: {
                        user: payload.user,
                        suffixes: payload.suffixes,
                    },
                    size: 'md',
                    onSubmit: () => {
                        const formEl = document.getElementById('user-edit-form') as HTMLFormElement | null;
                        if (!formEl) return;
                        const formData = new FormData(formEl);
                        formData.append('_method', 'put');
                        router.post(`/users/${payload.user.id}`, formData, {
                            preserveScroll: true,
                        });
                    }
                });
            },
        });
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

