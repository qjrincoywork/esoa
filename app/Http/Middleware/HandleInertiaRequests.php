<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\Models\Permission;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $authUser = $request->user();
        $userPermissions = $authUser
            ? (
                $authUser?->hasRole('superadmin')
                    ? Permission::all()->pluck('name')->toArray()
                    : $authUser->getAllPermissions()->pluck('name')->toArray()
              )
            : [];
        $navigationService = app(\App\Services\NavigationService::class);

        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => [
                    'id' => $authUser?->id,
                    'username' => $authUser?->username,
                    'email' => $authUser?->email,
                    'user_detail' => [
                        'account_type' => $authUser?->userDetail?->account_type,
                        'account_code' => $authUser?->userDetail?->account_code,
                        'branch_code' => $authUser?->userDetail?->branch_code,
                        'type' => $authUser?->userDetail?->type,
                        'suffix' => $authUser?->userDetail?->suffix,
                        'gender_id' => $authUser?->userDetail?->gender_id,
                        'civil_status_id' => $authUser?->userDetail?->civil_status_id,
                        'citizenship_id' => $authUser?->userDetail?->citizenship_id,
                        'department_id' => $authUser?->userDetail?->department_id,
                        'position_id' => $authUser?->userDetail?->position_id,
                        'first_name' => $authUser?->userDetail?->first_name,
                        'middle_name' => $authUser?->userDetail?->middle_name,
                        'last_name' => $authUser?->userDetail?->last_name,
                        'birthdate' => $authUser?->userDetail?->birthdate,
                        'employee_no' => $authUser?->userDetail?->employee_no,
                    ],
                ],
                'is_superadmin' => $authUser?->hasRole('superadmin'),
                'permissions' => $userPermissions,
            ],
            'navigations' => $navigationService->getNavigationsForUser($authUser),
            'sub_modules' => $navigationService->getReferencedModules(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
