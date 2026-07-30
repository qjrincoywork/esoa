import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    is_superadmin: boolean;
    permissions: Permissions;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface NavigationModule {
    id: number;
    name: string;
    slug: string;
    url: string;
    icon: string;
    permission_id: number | null;
    permission_name: string | null;
}

export interface SoaActivity {
    id?: number;
    user_id?: number;
    name?: string;
    event?: string;
    from?: unknown;
    to?: unknown;
    created_at?: string;
}

export interface Soa {
    id?: number;
    user_id?: number;
    soa_number?: string;
    soanum?: string;
    account_type?: string;
    account_code?: string;
    branch_code?: string;
    billing_ref?: string;
    billing_ref_from?: number | string;
    bill_type?: number;
    status?: number;
    status_color?: string;
    bill_date?: string;
    due_date?: string;
    /** Aging bucket label for the due date (e.g. "Past Due – 30 Days"). */
    due_in?: string;
    /** Semantic color utility classes for the aging badge. */
    due_in_color?: string;
    period_date_from?: string;
    period_date_to?: string;
    contract_date_from?: string;
    contract_date_to?: string;
    amount?: string | number;
    /** Numeric amount from API (e.g. SoaResource); preferred for math when present */
    amount_raw?: number;
    amount_paid?: number;
    payment_adjustment?: number;
    balance?: number;
    file_pdf?: string;
    file_xls?: string;
    deleted_at?: string | null;
    soa_activities?: SoaActivity[];
}

export interface Navigation {
    id: number;
    name: string;
    label: string;
    icon: string;
    modules: NavigationModule[];
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
};

export interface UserAccount {
    account_type?: string | null;
    account_code: string;
    branch_code?: string | null;
}

export interface User {
    id: number;
    username: string;
    email: string;
    user_detail: UserDetail;
    /** Populated only for GROUP_ACCOUNT_ADMIN users (type 4). */
    user_accounts?: UserAccount[] | null;
    is_superadmin: boolean;
    permissions: Permissions[];
}

export interface UserDetail {
    /** Integer user type (UserType enum). */
    type: number;
    /** Populated only for ACCOUNT_BRANCH_ADMIN (type 2), derived from user_accounts. */
    account_type?: string | null;
    account_code?: string | null;
    branch_code?: string | null;
    gender_id: number;
    civil_status_id: number;
    citizenship_id: number;
    department_id: number;
    position_id: number;
    first_name: string;
    middle_name: string;
    last_name: string;
    suffix: string | number;
    birthdate: string;
    employee_no?: string;
    has_employee_no?: boolean;
    email_verified_at: string;
}

export interface Permissions {
    permissions: Array<string>;
}

/* ── Dashboard ─────────────────────────────────────────────────────────────── */

/** Semantic tone a mark/tile may wear; resolved to a `--viz-*` custom property. */
export type VizTone =
    | 'neutral'
    | 'good'
    | 'warning'
    | 'serious'
    | 'critical'
    | 'status-unpaid'
    | 'status-endorsed'
    | 'status-paid'
    | 'status-disputed'
    | 'series-1'
    | 'series-2'
    | 'series-3'
    | 'muted';

export interface DashboardStat {
    key: string;
    label: string;
    hint?: string;
    value: number;
    formatted: string;
    format: 'currency' | 'number' | 'percent';
    tone: VizTone;
}

export interface DashboardSummary {
    outstanding: DashboardStat;
    billed: DashboardStat;
    collected: DashboardStat;
    collection_rate: DashboardStat;
    invoices: DashboardStat;
    /** Invoices in a past-due aging bucket — equals the past-due bars of the aging chart. */
    past_due: DashboardStat;
}

/** One bar/slice of a bucketed chart (aging bucket or invoice status). */
export interface MetricBucket {
    key: string;
    type: 'aging' | 'status';
    value: number;
    label: string;
    count: number;
    amount: number;
    amount_formatted: string;
    badge_class: string;
    tone: VizTone | null;
    emphasis: boolean;
    href: string;
}

export interface TrendPoint {
    key: string;
    label: string;
    count: number;
    billed: number;
    collected: number;
    billed_formatted: string;
    collected_formatted: string;
}

export interface TrendSeries {
    key: 'billed' | 'collected';
    label: string;
    token: VizTone;
}

export interface BillingTrend {
    granularity: 'day' | 'month';
    granularity_label: string;
    series: TrendSeries[];
    points: TrendPoint[];
}

export interface TopAccount {
    key: string;
    account_code: string;
    label: string;
    count: number;
    billed_amount: number;
    billed_formatted: string;
    outstanding_amount: number;
    outstanding_formatted: string;
    href: string;
}

/** The period the reported data actually spans, used to explain an empty result. */
export interface DashboardDataWindow {
    first_at: string;
    last_at: string;
    label: string;
}

export interface UserReportRow {
    key: string;
    user_id: number;
    /** How this row's invoices were attributed (App\Enums\DataScope). */
    scope: number;
    scope_label: string;
    scope_description: string;
    username: string;
    name: string;
    email: string | null;
    type_label: string;
    roles: string[];
    is_active: boolean;
    invoice_count: number;
    billed_amount: number;
    billed_formatted: string;
    outstanding_amount: number;
    outstanding_formatted: string;
    concern_count: number;
    payment_count: number;
    last_activity_at: string | null;
}

export interface DashboardFilters {
    preset: string;
    /** Null when the active preset is unbounded ("All time"). */
    date_from: string | null;
    date_to: string | null;
    user_id: number | null;
    account_code: string | null;
    granularity: 'day' | 'month';
    label: string;
}

export interface DashboardFilterOptions {
    presets: Array<{ value: string; name: string }>;
    users: Array<{ value: number; name: string }>;
}

export type BreadcrumbItemType = BreadcrumbItem;
