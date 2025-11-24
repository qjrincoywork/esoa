# Using `can()` and `authorize()` with Spatie Permissions

## Overview

Your `User` model now implements `Illuminate\Contracts\Auth\Access\Authorizable` and uses the `Authorizable` trait, which provides the `can()` and `authorize()` methods that work seamlessly with Spatie Permissions.

## How It Works

### 1. Spatie Auto-Registers Permissions as Gates

Spatie automatically registers all permissions in your database as Gates. This means:
- Every permission name becomes a Gate ability
- You can use `$user->can('permission-name')` directly
- No manual Gate registration needed

### 2. Using `can()` Method

The `can()` method checks if a user has a specific permission (via roles or direct assignment).

#### In Models/Controllers:

```php
// Check if user has a permission
if (auth()->user()->can('access.users')) {
    // User has permission
}

// Or with a specific user
$user = User::find(1);
if ($user->can('access.users')) {
    // User has permission
}

// Check multiple permissions (user needs ALL)
if ($user->can('access.users') && $user->can('users.create')) {
    // User has both permissions
}
```

#### In Blade Templates:

```blade
@can('access.users')
    <a href="/users">Users</a>
@endcan

@canany(['access.users', 'access.roles'])
    <!-- User has at least one of these permissions -->
@endcanany
```

### 3. Using `authorize()` Method

The `authorize()` method throws an `AuthorizationException` if the user doesn't have permission. Use this in controllers.

#### In Controllers:

```php
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index()
    {
        // Throws 403 if user doesn't have permission
        $this->authorize('access.users');
        
        // Or using Gate facade
        Gate::authorize('access.users');
        
        // Continue with logic...
    }
    
    public function create()
    {
        $this->authorize('users.create');
        // ...
    }
    
    public function store(Request $request)
    {
        $this->authorize('users.create');
        // ...
    }
}
```

#### With Policies (Advanced):

```php
// In a Policy
public function view(User $user, Model $model)
{
    return $user->can('access.users');
}

// In Controller
$this->authorize('view', $model);
```

### 4. Using in Middleware

Your `CheckPermission` middleware can be simplified:

```php
public function handle(Request $request, Closure $next, string $permission): Response
{
    $user = auth()->user();
    
    if (!$user || !$user->can($permission)) {
        abort(403, 'You do not have permission to access this resource.');
    }
    
    return $next($request);
}
```

### 5. Route Protection

You can use `can` middleware in routes:

```php
// Single permission
Route::get('/users', [UserController::class, 'index'])
    ->middleware('can:access.users');

// Multiple permissions (user needs ALL)
Route::get('/users', [UserController::class, 'index'])
    ->middleware('can:access.users,users.create');
```

### 6. Superadmin Bypass

To allow superadmins to bypass all checks, add this to your `AuthServiceProvider`:

```php
public function boot(): void
{
    Gate::before(function ($user, $ability) {
        // Superadmin bypasses all permission checks
        if ($user->hasRole('superadmin')) {
            return true;
        }
    });
}
```

## Examples

### Example 1: Controller with Authorization

```php
class UserController extends Controller
{
    public function index()
    {
        $this->authorize('access.users');
        
        $users = User::paginate(10);
        return view('users.index', compact('users'));
    }
    
    public function create()
    {
        $this->authorize('users.create');
        return view('users.create');
    }
    
    public function store(Request $request)
    {
        $this->authorize('users.create');
        
        // Validation and creation logic...
    }
    
    public function destroy(User $user)
    {
        $this->authorize('users.delete');
        
        $user->delete();
        return redirect()->route('users.index');
    }
}
```

### Example 2: Conditional Logic

```php
public function show(User $user)
{
    // Check permission
    if (auth()->user()->can('users.view')) {
        return view('users.show', compact('user'));
    }
    
    // Or use authorize (throws exception if not authorized)
    $this->authorize('users.view');
    
    return view('users.show', compact('user'));
}
```

### Example 3: Multiple Permissions

```php
public function edit(User $user)
{
    // User needs both permissions
    if (auth()->user()->can('users.view') && auth()->user()->can('users.update')) {
        return view('users.edit', compact('user'));
    }
    
    abort(403);
}
```

### Example 4: In Request Classes

```php
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.create');
    }
    
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
        ];
    }
}
```

## Important Notes

1. **Permission Names**: Use consistent naming (e.g., `access.users`, `users.create`, `users.update`)

2. **Caching**: Spatie caches permissions. Clear cache after changes:
   ```bash
   php artisan permission:cache-reset
   ```

3. **Superadmin**: Consider adding a Gate::before() to allow superadmins to bypass all checks

4. **Performance**: `can()` checks are cached, so they're fast even with many permissions

5. **Direct vs Role Permissions**: `can()` checks both:
   - Direct permissions (via `model_has_permissions`)
   - Role permissions (via `role_has_permissions`)

## Troubleshooting

**`can()` returns false even though user has permission:**
- Clear permission cache: `php artisan permission:cache-reset`
- Check permission name matches exactly
- Verify user has permission: `$user->hasPermissionTo('permission-name')`

**`authorize()` throws 403:**
- This is expected behavior - user doesn't have permission
- Check permission is assigned to user or their role
- Verify permission name is correct

