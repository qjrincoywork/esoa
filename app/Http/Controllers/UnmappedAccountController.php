<?php

namespace App\Http\Controllers;

use App\Enums\AccountCodePrefix;
use App\Enums\AccountDirectoryScope;
use App\Enums\AccountStanding;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\IsActive;
use App\Enums\Server;
use App\Helpers\CommonHelper;
use App\Helpers\CustomResponse;
use App\Helpers\SqlDatabase;
use App\Http\Requests\UnmappedAccount\BranchListRequest;
use App\Http\Requests\UnmappedAccount\DetailRequest;
use App\Http\Requests\UnmappedAccount\ListRequest;
use App\Http\Requests\UnmappedAccount\MappedUserListRequest;
use App\Http\Requests\UnmappedAccount\MemberListRequest;
use App\Http\Resources\AccountDirectoryDetailResource;
use App\Http\Resources\BranchDirectoryDetailResource;
use App\Http\Resources\CommonResource;
use App\Http\Resources\DirectoryMemberResource;
use App\Http\Resources\MappedUserResource;
use App\Http\Resources\UnmappedAccountResource;
use App\Http\Resources\UnmappedBranchResource;
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
     * Accounts and branches are two views of one listing rather than two pages: they
     * take the same filters, and the account classes excluded from user access are the
     * same on both ({@see AccountCodePrefix::excludedFromUserAccess()}), so a row shown
     * here is always one the mapping pickers would offer.
     *
     * Only the scope being viewed is queried. The listing spans a directory of tens of
     * thousands of rows on a remote database, so answering the tab nobody is looking at
     * would double the cost of every page.
     *
     * Access control (RBAC): the route sits behind the superadmin role group, and
     * {@see ListRequest::authorize()} states the same audience independently.
     *
     * @return \Inertia\Response
     */
    public function index(ListRequest $request)
    {
        $scope = $request->scope();
        $params = $request->lookupParams();
        $hms = new $this->sqlDatabase(Server::HMS);

        if ($scope === AccountDirectoryScope::BRANCH) {
            $branches = $hms->getUnassignedBranchesByParams($params);

            // Each branch is labelled with the account it belongs to; resolve the
            // page's codes in one lookup so the resource reads them from the memo.
            CommonHelper::primeAccountNames($branches->getCollection()->pluck('br_ac_code'));

            $directory = new CommonResource(UnmappedBranchResource::collection($branches));
        } else {
            $directory = new CommonResource(
                UnmappedAccountResource::collection($hms->getUnassignedAccountsByParams($params))
            );
        }

        return Inertia::render('unmapped_accounts/Index', [
            'directory' => $directory,
            'scope' => $scope,
            'filter_options' => [
                'scopes' => AccountDirectoryScope::list(),
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
