import type { AccountDetail, BranchDetail, DirectoryMember, DirectoryRow, MappedUser } from '@/composables/unmappedAccounts';

/** A labelled row of a detail list; blanks are shown as an em dash rather than hidden. */
export type Fact = { label: string; value: string | null | undefined; mono?: boolean };

/**
 * The full set of facts for each kind of record the directory pane lists.
 *
 * Kept as plain builders, shared by the pane's own "Details" tab and the top pane a
 * clicked row opens, so a record reads the same way wherever it is shown.
 */
export const accountFacts = (a: AccountDetail): Fact[] => [
  { label: 'Account Code', value: a.account_code, mono: true },
  { label: 'Account Name', value: a.account_name },
  { label: 'Main Account', value: a.main_account_code, mono: true },
  { label: 'Code Prefix', value: a.code_prefix, mono: true },
  { label: 'Type', value: a.account_type_label },
  { label: 'HMS Account Type', value: a.hms_account_type, mono: true },
  { label: 'Branches', value: a.branch_count.toLocaleString() },
  { label: 'TIN', value: a.tin, mono: true },
  { label: 'Address', value: a.address },
  { label: 'Contact Person', value: a.contact_person },
  { label: 'Contact Number', value: a.contact_number },
  { label: 'Agent Code', value: a.agent_code, mono: true },
  { label: 'Effectivity Date', value: a.effectivity_date },
  { label: 'Renewal Date', value: a.renewal_date },
  { label: 'Expiry Date', value: a.expiry_date },
  { label: 'Cancel Date', value: a.cancel_date },
  { label: 'Cancel Reason', value: a.cancel_reason },
];

export const branchFacts = (b: BranchDetail): Fact[] => [
  { label: 'Branch Code', value: b.branch_code, mono: true },
  { label: 'Branch Name', value: b.branch_name },
  { label: 'Account Code', value: b.account_code, mono: true },
  { label: 'Account Name', value: b.account_name },
  { label: 'Main Account', value: b.main_account_code, mono: true },
  { label: 'Code Prefix', value: b.code_prefix, mono: true },
  { label: 'Type', value: b.account_type_label },
  { label: 'Account Expiry Date', value: b.account_expiry_date },
  { label: 'TIN', value: b.tin, mono: true },
  { label: 'Address', value: b.address },
  { label: 'Attention', value: b.attention },
  { label: 'Position', value: b.position },
];

/** What only a listing row knows about a branch — its member count and who has it. */
export const branchRowFacts = (row: DirectoryRow): Fact[] => [
  { label: 'Members', value: Number(row.member_count ?? 0).toLocaleString() },
  { label: 'Mapped Users', value: row.mapped_users?.length ? row.mapped_users.join(', ') : 'Unmapped' },
];

export const memberFacts = (m: DirectoryMember): Fact[] => [
  { label: 'Name', value: m.name },
  { label: 'Policy Number', value: m.policy_number, mono: true },
  { label: 'Last Name', value: m.last_name },
  { label: 'First Name', value: m.first_name },
  { label: 'Middle Name', value: m.middle_name },
  { label: 'Sex', value: m.sex },
  { label: 'Birth Date', value: m.birth_date },
  { label: 'Plan', value: m.plan_code, mono: true },
  { label: 'Effectivity Date', value: m.effectivity_date },
  { label: 'Coverage Until', value: m.expiry_date },
  { label: 'Account Code', value: m.account_code, mono: true },
  { label: 'Branch Code', value: m.branch_code, mono: true },
  { label: 'Branch Name', value: m.branch_name },
];

export const mappedUserFacts = (u: MappedUser): Fact[] => [
  { label: 'Username', value: u.username },
  { label: 'Email', value: u.email },
  { label: 'Status', value: u.is_active ? 'Active' : 'Inactive' },
  { label: 'Account Type', value: u.account_type },
  { label: 'Account Code', value: u.account_code, mono: true },
  { label: 'Coverage', value: u.mapped_in_full ? 'Whole account' : 'Single branch' },
  { label: 'Branch Code', value: u.branch_code, mono: true },
  { label: 'Mapped', value: u.mapped_at },
];
