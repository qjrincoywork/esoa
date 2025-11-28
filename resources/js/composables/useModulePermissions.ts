import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export interface ModulePermissionsOptions {
  /**
   * Optional slug to use instead of deriving from page component name.
   * If not provided, will be derived from page.component (e.g., "Users/Index" -> "users")
   */
  slug?: string;
}

/**
 * Composable for checking module-based permissions.
 * 
 * @example
 * ```ts
 * // Basic usage - auto-detects slug from page component
 * const { slug, authPermissions, hasPermission, canCreate, canEdit, canDelete } = useModulePermissions();
 * 
 * // With custom slug
 * const { hasPermission, canAction } = useModulePermissions({ slug: 'custom-module' });
 * 
 * // Check custom actions
 * const canGeneratePdf = computed(() => hasPermission([`${slug.value}.generate-pdf`]));
 * const canUploadPdf = computed(() => hasPermission([`${slug.value}.upload-pdf`]));
 * ```
 */
export function useModulePermissions(options: ModulePermissionsOptions = {}) {
  const page = usePage();

  /**
   * The module slug derived from the page component name or provided via options.
   * Example: "Users/Index" -> "users"
   */
  const slug = computed(() => {
    if (options.slug) {
      return options.slug.toLowerCase();
    }

    const componentName = page.component;
    if (!componentName) {
      return '';
    }

    // Example: "Users/Index" -> "users"
    return componentName.split('/')[0].toLowerCase();
  });

  /**
   * All permissions for the current module, filtered by the slug prefix.
   * Example: For slug "users", returns permissions like ["users.create", "users.edit", etc.]
   */
  const authPermissions = computed<string[]>(() => {
    const perms = (page.props as any).auth?.permissions ?? [];
    const moduleSlug = slug.value;

    if (!moduleSlug) {
      return [];
    }

    return perms.filter((p: string) => p.startsWith(`${moduleSlug}.`));
  });

  /**
   * Check if the user has any of the specified permissions.
   * 
   * @param permissionsToCheck - Single permission string or array of permission strings
   * @returns true if user has at least one of the specified permissions
   * 
   * @example
   * ```ts
   * hasPermission('users.create')
   * hasPermission(['users.create', 'users.store'])
   * ```
   */
  const hasPermission = (permissionsToCheck: string | string[]): boolean => {
    const checks = Array.isArray(permissionsToCheck) ? permissionsToCheck : [permissionsToCheck];

    return checks.some((permission) => {
      if (!permission) {
        return false;
      }
      return authPermissions.value.includes(permission);
    });
  };

  /**
   * Check if user can create (has create or store permission)
   */
  const canCreate = computed(() =>
    hasPermission([`${slug.value}.create`, `${slug.value}.store`])
  );

  /**
   * Check if user can edit (has edit or update permission)
   */
  const canEdit = computed(() =>
    hasPermission([`${slug.value}.edit`, `${slug.value}.update`])
  );

  /**
   * Check if user can delete (has delete or destroy permission)
   */
  const canDelete = computed(() =>
    hasPermission([`${slug.value}.delete`, `${slug.value}.destroy`])
  );

  /**
   * Generic function to check any custom action permission.
   * Useful for actions beyond the basic CRUD operations.
   * 
   * @param action - The action name (e.g., 'generate-pdf', 'upload-pdf', 'update-access')
   * @param alternativeNames - Optional alternative permission names to check
   * @returns computed ref that checks if user has the permission
   * 
   * @example
   * ```ts
   * const canGeneratePdf = canAction('generate-pdf');
   * const canUploadPdf = canAction('upload-pdf', ['upload-pdf', 'upload']);
   * const canUpdateAccess = canAction('update-access');
   * ```
   */
  const canAction = (action: string, alternativeNames?: string[]): ReturnType<typeof computed<boolean>> => {
    const permissions = [`${slug.value}.${action}`];
    if (alternativeNames) {
      permissions.push(...alternativeNames.map(name => `${slug.value}.${name}`));
    }
    return computed(() => hasPermission(permissions));
  };

  return {
    slug,
    authPermissions,
    hasPermission,
    canCreate,
    canEdit,
    canDelete,
    canAction,
  };
}

