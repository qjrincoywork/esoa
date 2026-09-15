<?php

namespace App\Http\Controllers;

use App\Enums\AccountCodePrefix;
use App\Enums\AccountDirectoryScope;
use App\Enums\AccountType;
use App\Enums\Server;
use App\Helpers\CommonHelper;
use App\Helpers\SqlDatabase;
use App\Http\Requests\UnmappedAccount\ListRequest;
use App\Http\Resources\CommonResource;
use App\Http\Resources\UnmappedAccountResource;
use App\Http\Resources\UnmappedBranchResource;
use Inertia\Inertia;

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
            ],
        ]);
    }
}
