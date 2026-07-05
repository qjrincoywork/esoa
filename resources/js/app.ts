import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import Loader from "@/components/Loader.vue";
import { setMetaCsrfToken } from './lib/csrf';

const appName = import.meta.env.VITE_APP_NAME || 'VC';

const syncCsrfTokenFromPage = (page: unknown): void => {
    if (!page || typeof page !== 'object' || !('props' in page)) {
        return;
    }

    const props = (page as { props?: { csrf_token?: unknown } }).props;
    const token = props?.csrf_token;
    if (typeof token === 'string' && token.length > 0) {
        setMetaCsrfToken(token);
    }
};

const bootstrap = async (): Promise<void> => {
    createInertiaApp({
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) =>
            resolvePageComponent(
                `./pages/${name}.vue`,
                import.meta.glob<DefineComponent>('./pages/**/*.vue'),
            ),
        setup({ el, App, props, plugin }) {
            syncCsrfTokenFromPage(props.initialPage);

            router.on('success', (event) => {
                syncCsrfTokenFromPage(event.detail.page);
            });

            // Safety net: if an expired session slips through as a raw 401/419
            // (unauthenticated / CSRF mismatch), send the user to login with a full reload.
            router.on('invalid', (event) => {
                const status = event.detail.response?.status;
                if (status === 401 || status === 419) {
                    event.preventDefault();
                    window.location.href = '/login';
                }
            });

            createApp({ render: () => h(App, props) })
                .use(plugin)
                .component('Loader', Loader)
                .mount(el);
        },
        progress: {
            color: '#4B5563',
        },
    });
};

void bootstrap();

// This will set light / dark mode on page load...
initializeTheme();
