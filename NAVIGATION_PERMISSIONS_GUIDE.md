# Navigation & Permissions Integration Guide

## Overview

This system integrates Laravel Spatie Permissions with a dynamic navigation system. It allows you to:
- Create navigations and modules dynamically
- Control access to modules based on user permissions
- Automatically sync permissions with navigation modules

## Understanding Spatie Permission Tables

### `model_has_permissions` Table
**Purpose**: Direct permission assignment to users (bypassing roles)

**Use Case**: When you want to give a specific user a specific permission without creating a role for it.

**Example**:
```php
// Give user direct permission
$user->givePermissionTo('access.soa');

// This creates a record in model_has_permissions:
// permission_id | model_type | model_id
// 5            | App\Models\User | 123
```

### `model_has_roles` Table
**Purpose**: Role assignment to users

**Use Case**: Assign roles to users, and roles have permissions via `role_has_permissions`.

**Example**:
```php
// Assign role to user
$user->assignRole('admin');

// This creates a record in model_has_roles:
// role_id | model_type | model_id
// 2       | App\Models\User | 123
```

### How Permissions Work Together

1. **Via Roles** (Most Common):
   - User has Role → Role has Permissions → User inherits permissions
   - Checked via: `$user->hasPermissionTo('permission.name')`

2. **Direct Assignment**:
   - User has Permission directly (in `model_has_permissions`)
   - Checked via: `$user->hasPermissionTo('permission.name')` (same method!)

3. **Combined**:
   - User can have permissions from BOTH roles AND direct assignments
   - `$user->getAllPermissions()` returns all permissions from both sources

## Navigation System Structure

### Database Schema

```
navigations
├── id
├── name
├── description
├── icon
├── status
└── created_by

navigation_modules
├── id
├── navigation_id (FK → navigations.id)
├── permission_id (FK → permissions.id, nullable)
├── name
├── slug
├── menu_template
├── icon
├── status
└── created_by
```

### How It Works

1. **Navigation**: Top-level menu item (e.g., "Dashboard", "Settings")
2. **Navigation Module**: Sub-item under a navigation (e.g., "User Management" under "Settings")
3. **Permission Link**: Each module can optionally require a permission to access

## Usage Examples

### 1. Creating a Navigation with Modules

```php
use App\Models\Navigation;
use App\Models\NavigationModule;
use App\Services\NavigationService;

// Create navigation
$navigation = Navigation::create([
    'name' => 'Settings',
    'description' => 'Application settings',
    'icon' => 'settings',
    'status' => 1,
    'created_by' => auth()->id(),
]);

// Create module with permission
$module = NavigationModule::create([
    'navigation_id' => $navigation->id,
    'name' => 'User Management',
    'slug' => 'users',
    'menu_template' => 'users.index',
    'icon' => 'users',
    'status' => 1,
    'created_by' => auth()->id(),
]);

// Sync permission (creates permission if doesn't exist)
$navigationService = app(NavigationService::class);
$permission = $navigationService->syncModulePermission($module, 'access.users');
// This creates permission: "access.users" and links it to the module
```

### 2. Assigning Permissions to Roles

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Get or create role
$adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

// Get permission
$permission = Permission::where('name', 'access.users')->first();

// Give permission to role
$adminRole->givePermissionTo($permission);

// Now all users with 'admin' role can access the module
```

### 3. Assigning Permissions Directly to Users

```php
// Give user direct permission (bypasses roles)
$user->givePermissionTo('access.users');

// This creates a record in model_has_permissions
```

### 4. Checking Access in Controllers/Middleware

```php
// In your controller
public function index()
{
    // Middleware handles this, but you can also check:
    if (!auth()->user()->hasPermissionTo('access.users')) {
        abort(403);
    }
    
    // Or check if user can access module
    $module = NavigationModule::where('slug', 'users')->first();
    if (!$module->canBeAccessedBy(auth()->user())) {
        abort(403);
    }
}
```

### 5. Using in Routes

```php
// In routes/web.php
Route::middleware(['permission:access.users'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});
```

### 6. Getting Navigations for Frontend

The `NavigationService` automatically provides navigations in Inertia shared data:

```javascript
// In your Vue/React component
const navigations = usePage().props.navigations;

// navigations structure:
[
  {
    id: 1,
    name: "Settings",
    icon: "settings",
    modules: [
      {
        id: 1,
        name: "User Management",
        slug: "users",
        menu_template: "users.index",
        icon: "users",
        permission_id: 5,
        permission_name: "access.users"
      }
    ]
  }
]
```

## Access Control Logic

### Module Access Rules:

1. **No Permission Required** (`permission_id` is NULL):
   - Accessible to ALL authenticated users
   - Superadmins can always access

2. **Permission Required** (`permission_id` is set):
   - User must have the permission (via role OR direct assignment)
   - Superadmins can always access
   - Checked via: `$user->hasPermissionTo($permission)`

3. **Superadmin**:
   - Can access ALL modules regardless of permissions

### How `getAllPermissions()` Works:

```php
$user->getAllPermissions();
// Returns permissions from:
// 1. Roles (via role_has_permissions)
// 2. Direct assignments (via model_has_permissions)
```

## Best Practices

1. **Naming Convention**: Use `access.{module-slug}` for permissions (e.g., `access.users`, `access.soa`)

2. **Permission Management**: 
   - Create permissions when creating modules
   - Assign permissions to roles (not individual users when possible)
   - Use direct user permissions only for special cases

3. **Caching**: Navigation data is cached per user. Clear cache when updating:
   ```php
   $navigationService->clearCache($user);
   ```

4. **Module Status**: Set `status = 0` to hide modules without deleting them

5. **Superadmin Access**: Always check for superadmin role first in access control

## Migration Notes

If you already have data, you may need to:

1. Make `permission_id` nullable (already done in migration)
2. Create permissions for existing modules:
   ```php
   NavigationModule::whereNull('permission_id')->each(function ($module) {
       app(NavigationService::class)->syncModulePermission($module);
   });
   ```

## Troubleshooting

**Module not showing in navigation:**
- Check if `status = 1`
- Check if user has required permission
- Check if navigation has accessible modules
- Clear cache: `Cache::forget('navigations_user_' . $user->id)`

**Permission not working:**
- Verify permission exists: `Permission::where('name', 'access.users')->exists()`
- Check user has permission: `$user->hasPermissionTo('access.users')`
- Check role has permission: `$role->hasPermissionTo('access.users')`
- Clear permission cache: `php artisan permission:cache-reset`

