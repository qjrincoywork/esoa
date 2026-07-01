<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
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

        $userType      = $authUser?->userDetail?->type;
        $userAccounts  = $authUser?->userAccounts ?? collect();
        $firstAccount  = $userAccounts->first();

        // For ACCOUNT_BRANCH_ADMIN: surface the single account on user_detail for backwards-compat.
        // For GROUP_ACCOUNT_ADMIN: those fields are null on user_detail; full list surfaced separately.
        $sharedAccountType = $userType === UserType::ACCOUNT_BRANCH_ADMIN ? $firstAccount?->account_type : null;
        $sharedAccountCode = $userType === UserType::ACCOUNT_BRANCH_ADMIN ? $firstAccount?->account_code : null;
        $sharedBranchCode  = $userType === UserType::ACCOUNT_BRANCH_ADMIN ? $firstAccount?->branch_code  : null;

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
                        'account_type'   => $sharedAccountType,
                        'account_code'   => $sharedAccountCode,
                        'branch_code'    => $sharedBranchCode,
                        'type'           => $userType,
                        'has_employee_no' => !empty($authUser?->userDetail?->employee_no),
                    ],
                    'user_accounts' => $userType === UserType::GROUP_ACCOUNT_ADMIN
                        ? $userAccounts->map(fn ($ua) => [
                            'account_type' => $ua->account_type,
                            'account_code' => $ua->account_code,
                            'branch_code'  => $ua->branch_code,
                        ])->values()
                        : null,
                    'roles' => $authUser?->getRoleNames()->toArray(),
                    'email_verified_at' => $authUser?->email_verified_at,
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
