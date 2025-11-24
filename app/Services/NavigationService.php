<?php

namespace App\Services;

use App\Models\Navigation;
use App\Models\NavigationModule;
use Illuminate\Support\Facades\Cache;

class NavigationService
{
    /**
     * Get all navigations with their accessible modules for a user.
     * 
     * @param \App\Models\User|null $user
     * @return \Illuminate\Support\Collection
     */
    public function getNavigationsForUser($user = null)
    {
        $cacheKey = 'navigations_user_' . ($user ? $user->id : 'guest');

        $navigations = Navigation::where('status', 1)
            ->orderBy('id')
            ->get();

        return $navigations->map(function ($navigation) use ($user) {
            $modules = $navigation->accessibleModules($user);

            // Only return navigation if it has accessible modules
            if ($modules->isEmpty()) {
                return null;
            }

            return [
                'id' => $navigation->id,
                'name' => $navigation->name,
                'label' => $navigation->label,
                'icon' => $navigation->icon,
                'modules' => $modules,
                // 'modules' => $modules->map(function ($module) {
                //     return [
                //         'id' => $module->id,
                //         'name' => $module->name,
                //         'slug' => $module->slug,
                //         'url' => $module->url,
                //         'icon' => $module->icon,
                //         'ref_id' => $module->ref_id,
                //         'order_number'=> $module->order_number,
                //         'permission_id' => $module->permission_id,
                //         'permission_name' => $module->permission?->name,
                //         'sub_modules' => $module->subModules,
                //     ];
                // })->values(),
            ];
        })->filter()->values();
        // return Cache::remember($cacheKey, 3600, function () use ($user) {
        //     $navigations = Navigation::where('status', 1)
        //         ->orderBy('id')
        //         ->get();

        //     return $navigations->map(function ($navigation) use ($user) {
        //         $modules = $navigation->accessibleModules($user);

        //         // Only return navigation if it has accessible modules
        //         if ($modules->isEmpty()) {
        //             return null;
        //         }

        //         return [
        //             'id' => $navigation->id,
        //             'name' => $navigation->name,
        //             'description' => $navigation->description,
        //             'icon' => $navigation->icon,
        //             'modules' => $modules->map(function ($module) {
        //                 return [
        //                     'id' => $module->id,
        //                     'name' => $module->name,
        //                     'slug' => $module->slug,
        //                     'url' => $module->url,
        //                     'icon' => $module->icon,
        //                     'permission_id' => $module->permission_id,
        //                     'permission_name' => $module->permission?->name,
        //                 ];
        //             })->values(),
        //         ];
        //     })->filter()->values();
        // });
    }

    /**
     * Clear navigation cache for a specific user or all users.
     * 
     * @param \App\Models\User|null $user
     * @return void
     */
    public function clearCache($user = null)
    {
        if ($user) {
            Cache::forget('navigations_user_' . $user->id);
        } else {
            // Clear all navigation caches (you might want to use tags if available)
            // For now, we'll clear on navigation/module updates
        }
    }

    /**
     * Sync permission with navigation module.
     * Creates a permission if it doesn't exist and links it to the module.
     * 
     * @param \App\Models\NavigationModule $module
     * @param string|null $permissionName
     * @return \Spatie\Permission\Models\Permission|null
     */
    public function syncModulePermission(NavigationModule $module, ?string $permissionName = null)
    {
        if (!$permissionName) {
            // Generate permission name from module slug
            $permissionName = 'access.' . $module->slug;
        }

        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => $permissionName, 'guard_name' => 'web'],
            ['name' => $permissionName, 'guard_name' => 'web']
        );

        $module->update(['permission_id' => $permission->id]);
        
        $this->clearCache();

        return $permission;
    }
}

