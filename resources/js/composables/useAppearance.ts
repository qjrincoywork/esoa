import { onMounted, ref } from 'vue';

export type Appearance = 'light' | 'dark' | 'system' | 'light-warm' | 'dim';

export function updateTheme(value: Appearance) {
    if (typeof window === 'undefined') {
        return;
    }

    const html = document.documentElement;

    if (value === 'system') {
        const systemIsDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        html.classList.toggle('dark', systemIsDark);
        html.removeAttribute('data-theme');
        return;
    }

    // dim piggybacks on the dark class so Tailwind dark: utilities still activate,
    // then CSS variable overrides on html[data-theme="dim"] win by specificity.
    html.classList.toggle('dark', value === 'dark' || value === 'dim');

    if (value === 'dim' || value === 'light-warm') {
        html.setAttribute('data-theme', value);
    } else {
        html.removeAttribute('data-theme');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;

};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function initializeTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'system');

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('system');

export function useAppearance() {
    onMounted(() => {
        const savedAppearance = localStorage.getItem(
            'appearance',
        ) as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    return {
        appearance,
        updateAppearance,
    };
}
