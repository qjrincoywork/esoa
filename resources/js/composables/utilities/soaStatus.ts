import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { Auth } from '@/types';

/**
 * Which SOA statuses the signed-in user may set.
 *
 * The server decides this in {@see \App\Rules\SoaStatusIsValid}, and a form that offers
 * a different set is not a cosmetic difference: the reader picks a status, saves, and
 * the submission is rejected — or, worse, the status they wanted is not on the list at
 * all. Both SOA forms used to answer the question for themselves, from different
 * evidence, and disagreed with the server and with each other.
 *
 * Stated once here so the two forms cannot drift apart again, and written as a single
 * predicate so the two audiences are exact complements, exactly as the server has them.
 */

/** SOA statuses, mirroring {@see \App\Enums\SoaStatus}. */
export const SoaStatus = {
    UNPAID: 1,
    ENDORSED: 2,
    PAID: 3,
    DISPUTED: 4,
} as const;

/**
 * The statuses an account-scoped admin may set, mirroring
 * `config('vc.allowed_soa_status_for_account_branch_admin')`. Every other role may set
 * the rest — the server derives its list the same way round, from this same set.
 */
const ACCOUNT_ADMIN_STATUSES: readonly number[] = [SoaStatus.ENDORSED, SoaStatus.DISPUTED];

/**
 * The roles the server counts as account-scoped.
 *
 * Roles, not `has_employee_no`: that flag says whether someone is a VC employee, which
 * is a different question and answers "no" for a superadmin — who was therefore being
 * offered the account admin's statuses, none of which the server would accept from them.
 */
const ACCOUNT_ADMIN_ROLES: readonly string[] = ['account_branch_admin', 'group_account_admin'];

/** A `{value, name}` option as the server sends status lists. */
export type SoaStatusOption = { value: string | number; name: string };

/**
 * Narrow a status list to the statuses this user may actually set.
 *
 * @param statusTypes The full list from the server, normally `SoaStatus::list()`.
 */
export function useAssignableSoaStatuses(
    statusTypes: ComputedRef<SoaStatusOption[]>,
): ComputedRef<SoaStatusOption[]> {
    const page = usePage();

    const isAccountScopedAdmin = computed<boolean>(() => {
        const roles = ((page.props as { auth?: Auth }).auth?.user?.roles ?? []) as string[];

        return roles.some((role) => ACCOUNT_ADMIN_ROLES.includes(role));
    });

    return computed<SoaStatusOption[]>(() =>
        (statusTypes.value ?? []).filter(
            (status) => ACCOUNT_ADMIN_STATUSES.includes(Number(status.value)) === isAccountScopedAdmin.value,
        ),
    );
}
