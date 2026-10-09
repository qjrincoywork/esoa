<?php

namespace App\Http\Controllers;

use App\Enums\AccountCodePrefix;
use App\Enums\AccountDirectoryScope;
use App\Enums\AccountDirectoryView;
use App\Enums\AccountStanding;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\IsActive;
use App\Enums\MappingEligibility;
use App\Enums\Server;
use App\Enums\UserType;
use App\Helpers\CommonHelper;
use App\Helpers\CustomResponse;
use App\Helpers\SqlDatabase;
use App\Http\Requests\UnmappedAccount\AssignableUserListRequest;
use App\Http\Requests\UnmappedAccount\AssignUsersRequest;
use App\Http\Requests\UnmappedAccount\BranchListRequest;
use App\Http\Requests\UnmappedAccount\DetailRequest;
use App\Http\Requests\UnmappedAccount\ListRequest;
use App\Http\Requests\UnmappedAccount\MappedUserListRequest;
use App\Http\Requests\UnmappedAccount\MemberListRequest;
use App\Http\Resources\AccountDirectoryDetailResource;
use App\Http\Resources\AssignableUserResource;
use App\Http\Resources\BranchDirectoryDetailResource;
use App\Http\Resources\CommonResource;
use App\Http\Resources\DirectoryMemberResource;
use App\Http\Resources\MappedUserResource;
use App\Http\Resources\UnmappedAccountResource;
use App\Http\Resources\UnmappedBranchResource;
use App\Http\Resources\UnmappedDirectoryResource;
use App\Models\User;
use App\Models\UserAccount;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class UnmappedAccountController extends Controller
{
    /**
     * SqlDatabase class name, resolved per call for HMS lookups.
     *
     * @var string
     */
    protected $sqlDatabase;

    /**
     * UnmappedAccountController constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->sqlDatabase = SqlDatabase::class;
    }

    /**
     * Render the Inertia "unmapped_accounts/Index" page: the accounts and branches in
     * the HMS directory that no user has been given access to.
     *
     * The coverage gap in account mapping, read from the other end. The mapping screen
     * answers "what does this user have?"; this answers "what has nobody got?" — which
     * cannot be seen from any per-user view, because an account nobody is mapped to
     * appears on nobody's screen.
     *
     * Accounts and branches are views of one listing rather than two pages: they take
     * the same filters, and the account classes excluded from user access are the same
     * on both ({@see AccountCodePrefix::excludedFromUserAccess()}), so a row shown here
     * is always one the mapping pickers would offer. The default view lists both
     * together ({@see AccountDirectoryView}), so a name or code is searched across the
     * whole directory at once instead of one half at a time.
     *
     * Only what is being viewed is queried, and paged by HMS itself. The listing spans a
     * directory of tens of thousands of rows on a remote database, so answering a tab
     * nobody is looking at would double the cost of every page.
     *
     * A bulk lookup (many names or codes at once) also reports how many rows each entry
     * matched, under the other filters. That count is a closure so a partial reload that
     * only pages the list — or singles out one entry — can leave it out and skip it.
     *
     * Access control: permission-based, not role-based. The route's `check_permissions`
     * middleware and {@see ListRequest::authorize()} both require the permission named
     * after the route, granted through a role or directly to the user; superadmin
     * bypasses both.
     *
     * @return \Inertia\Response
     */
    public function index(ListRequest $request)
    {
        $view = $request->view();
        $params = $request->lookupParams();
        $hms = new $this->sqlDatabase(Server::HMS);

        [$page, $resource] = match ($view) {
            AccountDirectoryView::ACCOUNT => [$hms->getUnassignedAccountsByParams($params), UnmappedAccountResource::class],
            AccountDirectoryView::BRANCH => [$hms->getUnassignedBranchesByParams($params), UnmappedBranchResource::class],
            default => [$hms->getUnassignedDirectoryByParams($params), UnmappedDirectoryResource::class],
        };

        // Each branch is labelled with the account it belongs to; resolve the page's
        // codes in one lookup so the resource reads them from the memo. Account rows
        // carry no `br_ac_code`, so on an account page this primes nothing.
        CommonHelper::primeAccountNames($page->getCollection()->pluck('br_ac_code')->filter());

        return Inertia::render('unmapped_accounts/Index', [
            'directory' => new CommonResource($resource::collection($page)),
            'scope' => $view,
            'search_term_matches' => fn () => $hms->getUnassignedSearchTermMatches($view, $params),
            'max_search_terms' => config('vc.max_search_terms'),
            'filter_options' => [
                'scopes' => AccountDirectoryView::list(),
                'code_prefixes' => AccountCodePrefix::list(),
                'account_types' => AccountType::list(),
                'statuses' => IsActive::list(),
            ],
        ]);
    }

    /**
     * Return one directory row in full as JSON (AJAX only), for the detail pane.
     *
     * The listing carries only the handful of fields its columns show; the rest is
     * fetched for the one row a reader opened, so a page of twenty-five does not drag
     * twenty-five full records across from HMS for the twenty-four nobody looks at.
     *
     * A branch answers with its owning account alongside it, because a branch is only
     * ever mapped as an account/branch pair.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function details(DetailRequest $request)
    {
        $hms = new $this->sqlDatabase(Server::HMS);
        $code = $request->code();

        $detail = $request->scope() === AccountDirectoryScope::BRANCH
            ? $this->branchDetail($hms, $code, $request->excludedPrefixes())
            : $this->accountDetail($hms, $code, $request->excludedPrefixes());

        if (!$detail) {
            return CustomResponse::error('That directory record could not be found', Response::HTTP_NOT_FOUND);
        }

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['detail' => $detail]);
        }
    }

    /**
     * Return the members behind one directory row as JSON (AJAX only).
     *
     * Keyed on the same `cholders` column the listing's count was grouped by
     * ({@see MemberListRequest::memberColumn()}), so the total here is the number the
     * reader clicked on rather than a differently-derived approximation of it.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function members(MemberListRequest $request)
    {
        $hms = new $this->sqlDatabase(Server::HMS);

        if (!$this->isReachable($request->code(), $request->scope(), $hms, $request->excludedPrefixes())) {
            return CustomResponse::error('That directory record could not be found', Response::HTTP_NOT_FOUND);
        }

        $members = $hms->getDirectoryMembersByParams(
            $request->memberColumn(),
            $request->code(),
            $request->lookupParams()
        );

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'members' => new CommonResource(DirectoryMemberResource::collection($members)),
            ]);
        }
    }

    /**
     * Return one account's branches as JSON (AJAX only), for the "Branches" tab.
     *
     * Account-only: a branch has no branches of its own, and {@see BranchListRequest}
     * carries no scope to mistake for one. Shaped through the same
     * {@see UnmappedBranchResource} the main listing uses, so a branch reads the same
     * way whether it arrived via the directory or via one account's own pane.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function branches(BranchListRequest $request)
    {
        $hms = new $this->sqlDatabase(Server::HMS);
        $code = $request->code();

        if ($this->hasExcludedPrefix($code, $request->excludedPrefixes())) {
            return CustomResponse::error('That directory record could not be found', Response::HTTP_NOT_FOUND);
        }

        // Every row shares this one account code, so priming it once is all the
        // resource's account_name lookup ever needs.
        CommonHelper::primeAccountNames([$code]);

        $branches = $hms->getAccountBranchesByParams($code, $request->lookupParams());

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'branches' => new CommonResource(UnmappedBranchResource::collection($branches)),
            ]);
        }
    }

    /**
     * Return the users mapped to one account or branch as JSON (AJAX only), for the
     * "Mapped Users" tab.
     *
     * The question the main listing exists to leave unanswered, asked directly of one
     * code: not "what has nobody got" but "who already has this one". A branch is
     * resolved once, both to check it may be opened at all and to read the account it
     * belongs to, which the mapped-in-full half of the lookup needs.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function mappedUsers(MappedUserListRequest $request)
    {
        $hms = new $this->sqlDatabase(Server::HMS);
        $code = $request->code();

        if ($request->scope() === AccountDirectoryScope::BRANCH) {
            $branch = $hms->getBranchDirectoryDetail($code);

            if (!$branch || $this->hasExcludedPrefix((string) $branch->br_ac_code, $request->excludedPrefixes())) {
                return CustomResponse::error('That directory record could not be found', Response::HTTP_NOT_FOUND);
            }

            $users = $hms->getBranchMappedUsersByParams((string) $branch->br_ac_code, $code, $request->lookupParams());
        } else {
            if ($this->hasExcludedPrefix($code, $request->excludedPrefixes())) {
                return CustomResponse::error('That directory record could not be found', Response::HTTP_NOT_FOUND);
            }

            $users = $hms->getAccountMappedUsersByParams($code, $request->lookupParams());
        }

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'mapped_users' => new CommonResource(MappedUserResource::collection($users)),
            ]);
        }
    }

    /**
     * Return a page of users who could be given the selected rows (AJAX only), for the
     * "Assign users" picker.
     *
     * Only the types that mappings apply to are listed ({@see UserType::mappable()}),
     * searched by username, email or name. Each comes with how many mappings they hold
     * and their eligibility for exactly the rows selected ({@see MappingEligibility}),
     * counted for the whole page in two narrow queries
     * ({@see UserAccount::mappingSummaryFor()}). Nothing here touches HMS.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function assignableUsers(AssignableUserListRequest $request)
    {
        if (!$request->wantsJson() && !$request->ajax()) {
            return;
        }

        $filters = $request->filters();
        $search = $filters['search'];
        $keys = $request->targetKeys();

        $users = User::query()
            ->with('userDetail')
            ->whereHas('userDetail', fn ($query) => $query->whereIn('type', UserType::mappable()))
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('userDetail', fn ($detail) => $detail
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%"))))
            ->orderBy('username')
            ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);

        $summary = UserAccount::mappingSummaryFor($users->getCollection()->modelKeys(), $keys);

        $users->getCollection()->each(function (User $user) use ($summary, $keys): void {
            $user->mapping_count = $summary[$user->id]['mapped'] ?? 0;
            $user->held_count = $summary[$user->id]['held'] ?? 0;
            $user->requested_count = count($keys);
        });

        return response()->json([
            'users' => new CommonResource(AssignableUserResource::collection($users)),
        ]);
    }

    /**
     * Map the selected accounts/branches onto the chosen users.
     *
     * Every row goes to every user, skipping only what a user already holds, in one
     * transaction ({@see UserAccount::grantToUsers()}). The rows are checked against HMS
     * and the users against their mapping rules by {@see AssignUsersRequest} first, so
     * by the time anything is written every pair is known to be real and to fit.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignUsers(AssignUsersRequest $request)
    {
        $users = $request->users();
        $pairs = $request->targetPairs();

        DB::beginTransaction();

        try {
            $written = UserAccount::grantToUsers($users->modelKeys(), $pairs);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return CustomResponse::serverError($e, 'UnmappedAccountController::assignUsers');
        }

        $rows = count($pairs) === 1 ? '1 account or branch' : count($pairs) . ' accounts or branches';
        $people = $users->count() === 1 ? $users->first()->username : $users->count() . ' users';

        return response()->json([
            'status' => 'success',
            'message' => "Mapped {$rows} to {$people} ({$written} new " . ($written === 1 ? 'mapping' : 'mappings') . ')',
            'mapped_count' => $written,
        ], Response::HTTP_OK);
    }

    /**
     * One account, with the two counts that do not live on its row.
     *
     * @param  \App\Helpers\SqlDatabase  $hms
     * @param  array<int, string>  $excludedPrefixes
     * @return \App\Http\Resources\AccountDirectoryDetailResource|null
     */
    private function accountDetail($hms, string $code, array $excludedPrefixes)
    {
        $account = $hms->getAccountDirectoryDetail($code);

        if (!$account || $this->hasExcludedPrefix($code, $excludedPrefixes)) {
            return null;
        }

        $account->member_count = $hms->countMembersBy('ch_accountid', $code);
        $account->branch_count = $hms->countBranchesOfAccount($code);

        return new AccountDirectoryDetailResource($account);
    }

    /**
     * One branch, with its owning account and its member count.
     *
     * The account is looked up rather than joined — `ac_code` is not unique in HMS and
     * a few branches point at an account it no longer holds — so a branch whose account
     * has gone still answers, with the account reading as unknown and not in force.
     *
     * @param  \App\Helpers\SqlDatabase  $hms
     * @param  array<int, string>  $excludedPrefixes
     * @return \App\Http\Resources\BranchDirectoryDetailResource|null
     */
    private function branchDetail($hms, string $code, array $excludedPrefixes)
    {
        $branch = $hms->getBranchDirectoryDetail($code);

        if (!$branch || $this->hasExcludedPrefix((string) $branch->br_ac_code, $excludedPrefixes)) {
            return null;
        }

        $account = $hms->getAccountDirectoryDetail((string) $branch->br_ac_code);

        $branch->account_name = $account
            ? CommonHelper::convertStringEncoding(trim((string) $account->ac_name))
            : null;
        $branch->account_is_active = $account ? AccountStatus::isActive($account->ac_status) : false;
        // A branch has no standing or expiry of its own; it runs out with its account.
        $branch->account_standing = $account
            ? AccountStanding::resolve($account->ac_status, $account->ac_expiry)
            : AccountStanding::INACTIVE;
        $branch->account_expiry_date = $account ? CommonHelper::formatDate($account->ac_expiry) : null;
        $branch->member_count = $hms->countMembersBy('ch_branch_code', $code);

        return new BranchDirectoryDetailResource($branch);
    }

    /**
     * Whether a code may be opened at all.
     *
     * The listing hides the account classes that are never mapped to a user, so the
     * endpoints behind it must too — otherwise guessing an `IN-` code would read a
     * record the listing exists to keep out of reach.
     *
     * @param  \App\Helpers\SqlDatabase  $hms
     * @param  array<int, string>  $excludedPrefixes
     */
    private function isReachable(string $code, string $scope, $hms, array $excludedPrefixes): bool
    {
        if ($scope !== AccountDirectoryScope::BRANCH) {
            return !$this->hasExcludedPrefix($code, $excludedPrefixes);
        }

        $branch = $hms->getBranchDirectoryDetail($code);

        return $branch !== null && !$this->hasExcludedPrefix((string) $branch->br_ac_code, $excludedPrefixes);
    }

    /**
     * @param  array<int, string>  $excludedPrefixes
     */
    private function hasExcludedPrefix(string $accountCode, array $excludedPrefixes): bool
    {
        foreach ($excludedPrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($accountCode, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }
}
