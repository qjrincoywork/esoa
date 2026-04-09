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

export interface User {
  id: number;
  username: string;
  email: string;
  user_detail: UserDetail;
  is_superadmin: boolean;
  permissions: Permissions[];
}

export interface UserDetail {
  account_type: string;
  type: number;
  account_code: string;
  branch_code: string;
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
  employee_no: string;
}

export interface Permissions {
  permissions: Array<string>;
}

export type BreadcrumbItemType = BreadcrumbItem;
