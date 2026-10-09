import { toRef } from 'vue';
import { dispatchNotification } from '@/components/notification';
import { useAjax } from '@/composables/useAjax';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModal } from '@/composables/useModal';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { usePane } from '@/composables/usePane';
import AssignUsersForm from '@/components/forms/unmapped_accounts/AssignUsersForm.vue';
import UnmappedDirectoryPane from '@/components/forms/unmapped_accounts/UnmappedDirectoryPane.vue';
import type { MappingBadge } from '@/composables/users';

/** Which half of the directory a row belongs to; mirrors App\Enums\AccountDirectoryScope. */
export const DIRECTORY_SCOPE = { ACCOUNT: 'account', BRANCH: 'branch' } as const;

export type DirectoryScope = (typeof DIRECTORY_SCOPE)[keyof typeof DIRECTORY_SCOPE];

/**
 * What the listing shows: one kind of row, or both together; mirrors
 * App\Enums\AccountDirectoryView. A view is not a scope — no row is ever "all".
 */
export const DIRECTORY_VIEW = { ALL: 'all', ...DIRECTORY_SCOPE } as const;

export type DirectoryView = (typeof DIRECTORY_VIEW)[keyof typeof DIRECTORY_VIEW];

/**
 * Whether an account is in force, decided and presented by `App\Enums\AccountStanding`
 * (expiry outranks the HMS status letter). Rendered as sent — never re-derived here.
 */
export interface AccountStanding {
  value: 'active' | 'inactive' | 'expired';
  label: string;
  /** Badge classes (background + text). */
  color: string;
  /** Text-only classes for inline facts tied to the standing, e.g. the expiry date; may be empty. */
  text_color: string;
}

/** One row of the unmapped listing, as the two Unmapped*Resource classes shape it. */
export interface DirectoryRow {
  /** Which kind of row this is — what a listing of both kinds tells them apart by. */
  kind: DirectoryScope;
  /** The kind as a badge, decided and colored by `App\Enums\AccountMappingBadge`. */
  kind_badge: MappingBadge;
  account_code: string;
  account_name: string;
  branch_code: string | null;
  branch_name?: string | null;
  main_account_code?: string | null;
  code_prefix: string | null;
  account_type: string;
  account_type_label: string;
  /** For a branch row, its account's standing — a branch has none of its own. */
  is_active?: boolean;
  /** For a branch row, its account's standing. */
  standing?: AccountStanding;
  expiry_date?: string | null;
  member_count: number;
  /** Who already has this row — empty unless the listing was asked to include mapped rows. */
  mapped_users?: string[];
  is_mapped?: boolean;
}

/** An account opened up, as `AccountDirectoryDetailResource` shapes it. */
export interface AccountDetail {
  account_code: string;
  account_name: string;
  main_account_code: string | null;
  code_prefix: string | null;
  account_type: string;
  account_type_label: string;
  is_active: boolean;
  standing: AccountStanding;
  hms_account_type: string | null;
  address: string | null;
  tin: string | null;
  contact_person: string | null;
  contact_number: string | null;
  agent_code: string | null;
  effectivity_date: string | null;
  renewal_date: string | null;
  expiry_date: string | null;
  cancel_date: string | null;
  cancel_reason: string | null;
  member_count: number;
  branch_count: number;
}

/** A branch opened up, as `BranchDirectoryDetailResource` shapes it. */
export interface BranchDetail {
  branch_code: string;
  branch_name: string;
  account_code: string;
  account_name: string;
  account_is_active: boolean;
  /** The account's standing — a branch has no standing or expiry of its own. */
  standing: AccountStanding;
  account_expiry_date: string | null;
  main_account_code: string | null;
  code_prefix: string | null;
  account_type: string;
  account_type_label: string;
  address: string | null;
  tin: string | null;
  attention: string | null;
  position: string | null;
  member_count: number;
}

export type DirectoryDetail = AccountDetail | BranchDetail;

/** One cardholder, as `DirectoryMemberResource` shapes it. */
export interface DirectoryMember {
  id: number | string;
  policy_number: string | null;
  name: string;
  last_name: string;
  first_name: string;
  middle_name: string | null;
  sex: string | null;
  birth_date: string | null;
  plan_code: string | null;
  effectivity_date: string | null;
  expiry_date: string | null;
  account_code: string | null;
  branch_code: string | null;
  branch_name: string | null;
}

/** A server-paginated page of rows, shaped as `CommonResource` wraps a paginator. */
export interface DirectoryPage<T> {
  data: T[];
  current_page: number;
  per_page: number;
  total: number;
}

export type MemberPage = DirectoryPage<DirectoryMember>;

/** One branch of an account, as `UnmappedBranchResource` shapes it — the "Branches" tab reuses that resource, so it reuses `DirectoryRow` too. */
export type BranchPage = DirectoryPage<DirectoryRow>;

/** One user mapped to an account or branch, as `MappedUserResource` shapes it. */
export interface MappedUser {
  user_id: number;
  username: string | null;
  email: string | null;
  is_active: boolean;
  account_type: string | null;
  account_code: string;
  branch_code: string | null;
  /** Whether this mapping grants the whole account rather than one specific branch of it. */
  mapped_in_full: boolean;
  mapped_at: string | null;
}

export type MappedUserPage = DirectoryPage<MappedUser>;

/**
 * One row as "Assign users" carries it: the pair a mapping stores. A null branch code
 * maps the whole account. Matches `Concerns\ReadsMappingTargets`.
 */
export interface MappingTarget {
  account_code: string;
  branch_code: string | null;
}

/** Whether a user can take the selected rows, decided by `App\Enums\MappingEligibility`. */
export interface MappingEligibility extends MappingBadge {
  value: 'eligible' | 'partially_mapped' | 'already_mapped' | 'limit_reached' | 'not_mappable';
  /** Picking the user would map something: there is something to add and room for it. */
  assignable: boolean;
}

/** One user in the "Assign users" picker, as `AssignableUserResource` shapes it. */
export interface AssignableUser {
  id: number;
  username: string;
  email: string | null;
  full_name: string | null;
  is_active: boolean;
  type: number | null;
  type_label: string;
  mapping_count: number;
  /** The type's cap on mappings; null when unlimited. */
  mapping_limit: number | null;
  /** How many of the selected rows the user already holds. */
  held_count: number;
  eligibility: MappingEligibility;
}

export type AssignableUserPage = DirectoryPage<AssignableUser> & { last_page: number };

const EMPTY_PAGE = { data: [], current_page: 1, per_page: 10, total: 0 };

/** The pair a listing row maps: a branch with its account, or the whole account. */
export const toMappingTarget = (row: DirectoryRow): MappingTarget => ({
  account_code: row.account_code,
  branch_code: row.kind === DIRECTORY_SCOPE.BRANCH ? row.branch_code : null,
});

/**
 * Opening one row of the unmapped listing.
 *
 * The listing shows enough to spot a coverage gap; the pane shows enough to decide what
 * to do about it. Both halves of that — the directory record and the people behind it —
 * are fetched only for the row actually opened, because the listing spans a directory
 * of tens of thousands of rows on a remote database.
 */
export function useUnmappedAccounts() {
  const { slug } = useModulePermissions();
  const { get, post } = useAjax();
  const { openModal, closeModal } = useModal();
  const {
    openPane,
    closePane,
    setPaneLoading,
    setPaneError,
    setPaneContent,
    rightPane,
  } = usePane();

  const rightPaneVisible = toRef(rightPane, 'open');
  const rightPaneTitle = toRef(rightPane, 'title');
  const rightPaneLoading = toRef(rightPane, 'loading');
  const rightPaneError = toRef(rightPane, 'error');
  const rightPaneContentComponent = toRef(rightPane, 'contentComponent');
  const rightPaneComponentProps = toRef(rightPane, 'componentProps');

  /** The code that identifies a row — a branch by its own, an account by its account's. */
  const codeOf = (row: DirectoryRow, scope: DirectoryScope): string =>
    scope === DIRECTORY_SCOPE.BRANCH ? String(row.branch_code ?? '') : String(row.account_code ?? '');

  /** Fetch the full directory record behind one row. */
  const getDirectoryDetail = async (
    scope: DirectoryScope,
    code: string,
  ): Promise<DirectoryDetail | null> => {
    const response = await get<{ detail: DirectoryDetail }>(`/${slug.value}/details`, { scope, code });

    if (!response.ok) {
      throw new Error('Failed to fetch the directory record');
    }

    return response.data?.detail ?? null;
  };

  /**
   * Fetch a page of the members behind one row.
   *
   * Exposed for the pane to call again as the reader pages or searches, rather than
   * loading every cardholder up front — an account here can hold ten thousand.
   */
  const getDirectoryMembers = async (
    scope: DirectoryScope,
    code: string,
    params: Record<string, string | number> = {},
  ): Promise<MemberPage> => {
    const response = await get<{ members: MemberPage }>(`/${slug.value}/members`, {
      scope,
      code,
      ...params,
    });

    if (!response.ok) {
      throw new Error('Failed to fetch members');
    }

    return response.data?.members ?? EMPTY_PAGE;
  };

  /**
   * Fetch a page of one account's branches, for the pane's "Branches" tab.
   *
   * Reuses the `UnmappedBranchResource` shape the main directory listing already
   * returns, so the tab's columns and row type are shared with it too.
   */
  const getAccountBranches = async (
    accountCode: string,
    params: Record<string, string | number> = {},
  ): Promise<BranchPage> => {
    const response = await get<{ branches: BranchPage }>(`/${slug.value}/branches`, {
      code: accountCode,
      ...params,
    });

    if (!response.ok) {
      throw new Error('Failed to fetch branches');
    }

    return response.data?.branches ?? EMPTY_PAGE;
  };

  /**
   * Fetch a page of the users mapped to one account or branch, for the pane's
   * "Mapped Users" tab — the question the main listing exists to leave unanswered,
   * asked directly of the one row a reader opened.
   */
  const getMappedUsers = async (
    scope: DirectoryScope,
    code: string,
    params: Record<string, string | number> = {},
  ): Promise<MappedUserPage> => {
    const response = await get<{ mapped_users: MappedUserPage }>(`/${slug.value}/mapped_users`, {
      scope,
      code,
      ...params,
    });

    if (!response.ok) {
      throw new Error('Failed to fetch mapped users');
    }

    return response.data?.mapped_users ?? EMPTY_PAGE;
  };

  /**
   * Fetch a page of the users who could be given the selected rows, each with their
   * eligibility for exactly those rows. Posted because it carries the rows. Returns null
   * when the request fails.
   */
  const getAssignableUsers = async (
    targets: MappingTarget[],
    params: { search?: string; page?: number; per_page?: number } = {},
  ): Promise<AssignableUserPage | null> => {
    try {
      const response = await post<{ users: AssignableUserPage }>(`/${slug.value}/assignable_users`, {
        targets,
        ...params,
      });

      if (!response.ok) {
        throw new Error('Failed to fetch users');
      }

      return response.data?.users ?? null;
    } catch {
      dispatchNotification({ title: 'Error', content: 'Error fetching users', type: 'error' });

      return null;
    }
  };

  /**
   * Map the selected rows onto the chosen users. A rejection is reported, and its field
   * messages reach the open form through `useAjax`. Returns whether it was saved.
   */
  const assignUsers = async (targets: MappingTarget[], userIds: number[]): Promise<boolean> => {
    showLoader();

    try {
      const response = await post<{ message: string }>(`/${slug.value}/assign_users`, {
        targets,
        user_ids: userIds,
      });

      dispatchNotification({
        title: response.ok ? 'Success' : 'Error',
        content: response.data?.message ?? (response.ok ? 'Users assigned' : 'Could not assign the users'),
        type: response.ok ? 'success' : 'error',
      });

      return response.ok;
    } catch {
      dispatchNotification({ title: 'Error', content: 'Network error', type: 'error' });

      return false;
    } finally {
      hideLoader();
    }
  };

  /**
   * Open "Assign users" for one row or a selection of them.
   *
   * The form picks the users; this owns the save, so the modal stays open — selection
   * intact — when the server refuses, and closes only once the mapping is stored.
   */
  const openAssignUsers = (rows: DirectoryRow[], onAssigned?: () => void) => {
    if (!rows.length) return;

    const targets = rows.map(toMappingTarget);
    let form: { selectedUserIds: () => number[] } | null = null;
    const [first] = rows;

    openModal({
      modalTitle: rows.length === 1
        ? `Assign users to ${(first.kind === DIRECTORY_SCOPE.BRANCH ? first.branch_name : first.account_name) || codeOf(first, first.kind)}`
        : `Assign users to ${rows.length} accounts & branches`,
      buttonText: 'Assign',
      component: AssignUsersForm,
      componentProps: {
        rows,
        targets,
        onReady: (api: { selectedUserIds: () => number[] }) => {
          form = api;
        },
      },
      size: 'xl2',
      onSubmit: async () => {
        const userIds = form?.selectedUserIds() ?? [];

        if (!userIds.length) {
          dispatchNotification({ title: 'Error', content: 'Select at least one user to assign.', type: 'error' });
          return;
        }

        if (await assignUsers(targets, userIds)) {
          closeModal();
          onAssigned?.();
        }
      },
    });
  };

  /**
   * Open a row in the right pane.
   *
   * Only the record is awaited. The members tab fetches its own first page when it is
   * opened, so a reader who wanted the account details does not wait on a list of ten
   * thousand cardholders they never asked to see.
   */
  const openDirectoryRow = async (row: DirectoryRow, scope: DirectoryScope) => {
    const code = codeOf(row, scope);

    if (!code) return;

    showLoader();

    try {
      const detail = await getDirectoryDetail(scope, code);

      openPane({
        side: 'right',
        title: scope === DIRECTORY_SCOPE.BRANCH
          ? (row.branch_name || code)
          : (row.account_name || code),
        component: UnmappedDirectoryPane,
        componentProps: { row, scope, code, detail },
      });
    } catch {
      setPaneLoading('right', false);
      setPaneError('right', 'Error fetching the directory record.');
      setPaneContent('right', null);
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    } finally {
      hideLoader();
    }
  };

  return {
    getDirectoryDetail,
    getDirectoryMembers,
    getAccountBranches,
    getMappedUsers,
    getAssignableUsers,
    openAssignUsers,
    openDirectoryRow,
    closePane,
    rightPaneVisible,
    rightPaneTitle,
    rightPaneLoading,
    rightPaneError,
    rightPaneContentComponent,
    rightPaneComponentProps,
  };
}
