import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { index as homeIndex } from '@/routes/home';

/**
 * Where "home" links — the logo, the Dashboard nav item — take the signed-in user.
 *
 * The server resolves it with `App\Support\LandingRoute`, the same rules as the
 * post-login redirect, so the link only ever points at a page this user may open
 * (the admin dashboard for superadmins, the SOA dashboard for everyone permitted,
 * the home page otherwise). Falls back to the home page, which every signed-in user
 * can open, if the prop is missing.
 */
export function useLandingUrl() {
    const page = usePage();

    return computed<string>(() => page.props.auth?.landing_url ?? homeIndex().url);
}
