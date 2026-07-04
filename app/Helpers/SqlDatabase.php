<?php

namespace App\Helpers;

use App\Enums\AccountType;
use App\Enums\BillRefFrom;
use App\Enums\OrderType;
use App\Enums\Server;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Ramsey\Collection\Sort;

class SqlDatabase
{
    /**
     * DB instance.
     *
     * @var DB
     */
    protected $db;

    public function __construct($servername)
    {
        $this->db = DB::connection($servername);
    }

    /**
     * Retrieves a paginated list of SOA records from the database.
     *
     * @param array $params
     * @return \Illuminate\Pagination\Paginator
     */
    public function getSoas($params)
    {
        // Pagination
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $result = $this->db
            ->table('Upload')
            ->when(isset($params['soanum']), function ($query) use ($params) {
                $query->where('up_soanum', 'LIKE', '%' . $params['soanum'] . '%');
            })
            // ->select([
            //     'up_id',
            //     'up_soanum',
            //     'up_macode',
            //     'up_acname',
            //     'up_branch',
            //     'up_accode',
            //     'up_accode',
            //     'up_status',
            //     'up_endorsedtoacct',
            // ])
            ->orderBy('up_id', 'desc');

        if (auth()->user() && !auth()->user()->hasRole('superadmin')) {
            $result->whereNull('up_delete_date');
        }

        return $result->paginate($perPage);
    }

    /**
     * Retrieves a single SOA record by its ID.
     *
     * @param int $id
     * @return object|null
     */
    public function getSoa($id)
    {
        $result = $this->db
            ->table('Upload')
            ->where('up_id', $id)
            ->first();

        return $result;
    }

    /**
     * Retrieves a single account record by its accountCode.
     *
     * @param string $accountCode
     * @return object|null
     */
    public function getAccount($accountCode)
    {
        $result = $this->db
            ->table('Accounts')
            ->where('ac_code', $accountCode)
            ->first();

        return $result;
    }

    /**
     * Retrieves a single branch record by its branchCode.
     *
     * @param string $branchCode
     * @return object|null
     */
    public function getBranch($branchCode)
    {
        $result = $this->db
            ->table('Branches')
            ->where('br_code', $branchCode)
            ->first();

        return $result;
    }

    /**
     * Retrieves a single billing record by its reference ID.
     *
     * Excludes records that are currently being recomputed.
     *
     * @param string $billref Reference ID of the billing record
     * @return object|null
     */
    public function getBilling($billref)
    {
        $result = $this->db
            ->table('billing')
            ->select('bl_refid, bl_balance, bl_urgcol, bl_vat_amt, bl_urg_dateproc')
            ->like('bl_refid', $billref)
            ->whereNotIn('bl_refid', function ($builder) {
                return $builder
                    ->select('billing_id')
                    ->from('billing_recompute');
            })
            ->first();

        return $result;
    }

    /**
     * Retrieves accounts based on the specified type.
     *
     * @param string $type The type of accounts to retrieve
     * @return \Illuminate\Pagination\Paginator
     */
    public function getAccountsByParams($params)
    {
        // Pagination
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $selectedCode = $params['selected_code'] ?? null;
        $result = $this->db
            ->table('Accounts')
            ->select('ac_name', 'ac_code', 'ac_ma_code')
            ->when(isset($params['type']), function ($query) use ($params) {
                switch ($params['type']) {
                    case AccountType::TPA:
                        $query->where('ac_code', 'like', 'TP%');
                        break;
                    case AccountType::HMO:
                        $query->where('ac_code', 'not like', 'TP%');
                        break;
                    // default:
                        // $query->where('ac_code', 'not like', 'TP%');
                        // break;
                }
            })
            ->when(isset($params['name']) && $params['name'] !== '', function ($query) use ($params, $selectedCode) {
                $query->where(function ($nameQuery) use ($params, $selectedCode) {
                    $nameQuery->where('ac_name', 'like', '%' . $params['name'] . '%');
                    if (!empty($selectedCode)) {
                        $nameQuery->orWhere('ac_code', $selectedCode);
                    }
                });
            })
            ->where(function ($q) {
                $q->where('ac_code', 'not like', 'IN%')
                    ->where('ac_code', 'not like', 'FM%')
                    ->where('ac_code', 'not like', 'GR%');
            })
            ->where('ac_status', 'A') // Active accounts only
            ->groupBy('ac_name', 'ac_code', 'ac_ma_code')
            ->when(!empty($selectedCode), function ($query) use ($selectedCode) {
                $query->orderByRaw("CASE WHEN ac_code = ? THEN 0 ELSE 1 END", [$selectedCode]);
            })
            ->where(function ($query) {
                $query->where('ac_candate', '>', now())
                    ->orWhereNull('ac_candate');
            })
            ->orderBy('ac_name');

        return $result->paginate($perPage);
    }

    /**
     * Retrieves the account codes associated with a specific agent.
     *
     * This method queries the 'Accounts' table to fetch active account codes
     * for the given agent code, excluding accounts that have been cancelled
     * (where cancellation date is in the past or null).
     *
     * @param string $agentCode The agent code to filter accounts by.
     * @return \Illuminate\Support\Collection A collection of account codes (ac_code).
     */
    public function getAccountsOfAgent($agentCode)
    {
        $accounts = $this->db
            ->table('Accounts')
            ->select('ac_code')
            ->where('ac_agcode', $agentCode)
            ->where('ac_status', 'A') // Active accounts only
            ->where(function ($query) {
                $query->where('ac_candate', '>', now())
                    ->orWhereNull('ac_candate');
            })
            ->pluck('ac_code');

        return $accounts;
    }

    public function getBillingRefsByParams($params)
    {
        if ($params['billing_ref_from'] == BillRefFrom::CLAIMS) {
            $result = $this->getClaimsReceivingDetailsByParams($params);
        } else {
            $result = $this->getMdaDetailsByParams($params);
        }

        return $result;
    }

    public function getBillingByParams($params)
    {
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $result = $this->db
            ->table('Billing as a')
            ->select([
                'a.bl_refid',
                'a.bl_type',
                'a.bl_claimnum',
                'a.bl_policynum',
                'a.bl_balance',
                'a.bl_name',
                'a.bl_dateposted',
                'a.bl_workstatus',
            ])
            ->when(!empty($params['billing_ref']), function ($query) use ($params) {
                $billRefs = is_string($params['billing_ref'])
                    ? explode(',', $params['billing_ref'])
                    : $params['billing_ref'];
                $query->whereIn('a.bl_refid', $billRefs);
            })
            ->when(!empty($params['policynum']), function ($query) use ($params) {
                $query->where('a.bl_policynum', $params['policynum']);
            })
            ->orderBy('a.bl_dateposted', 'desc')
            ->paginate($perPage);

        return $result;
    }

    public function getCardHolderDetailsByParams($params)
    {
        $authUser = auth()->user();
        // Pagination
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $query = $this->db
            ->table('cholders as c')
            ->leftJoin('Accounts as ac', function ($join) use ($params) {
                $join->on('c.ch_accountid', '=', 'ac.ac_code');
            })
            ->leftJoin('claims as cl', function ($join) use ($params) {
                $join->on('c.ch_policynum', '=', 'cl.cl_policynumber');
            })
            ->leftJoin('Acctg as act', function ($join) use ($params) {
                $join->on('cl.cl_batchnumber', '=', 'act.act_batchnum');
            })
            ->select(
                'cl.cl_claimnum as claimnum',
                'act.act_dateposted',
                'act.act_batchnum',
                'cl.cl_policynumber',
                'c.ch_id',
                'c.ch_policynum',
                'c.ch_accountid',
                'c.ch_branch_code',
                'c.ch_firstname',
                'c.ch_lastname',
                'c.ch_middlename',
                'c.ch_suffix'
            );

        if ($authUser?->hasRole('broker')) {
            $agentAccounts = (new SqlDatabase(Server::HMS))
                ->getAccountsOfAgent($authUser->userDetail?->agent_code ?? null);
            $query->whereIn('c.ch_accountid', $agentAccounts);
        }

        if ($authUser?->hasRole('account_branch_admin')) {
            $firstAccount = $authUser->userAccounts->first();
            $query->where('c.ch_accountid', $firstAccount?->account_code ?? null);
            if (!empty($firstAccount?->branch_code)) {
                $query->where('c.ch_branch_code', $firstAccount->branch_code);
            }
        }

        if ($authUser?->hasRole('group_account_admin')) {
            $userAccounts = $authUser->userAccounts;
            if ($userAccounts->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($userAccounts) {
                    foreach ($userAccounts as $ua) {
                        $q->orWhere(function ($sub) use ($ua) {
                            $sub->where('c.ch_accountid', $ua->account_code);
                            if (!empty($ua->branch_code)) {
                                $sub->where('c.ch_branch_code', $ua->branch_code);
                            }
                        });
                    }
                });
            }
        }

        $result = $query
            ->when(!empty($params['billing_ref']), function ($query) use ($params) {
                $billRefs = is_string($params['billing_ref'])
                    ? explode(',', $params['billing_ref'])
                    : $params['billing_ref'];
                $query->whereIn('cl.cl_batchnumber', $billRefs);
            })
            ->when(!empty($params['account_code']), function ($query) use ($params) {
                $query->where('ac.ac_code', $params['account_code']);
            })
            ->when(!empty($params['branch_code']), function ($query) use ($params) {
                $query->where('c.ch_branch_code', $params['branch_code']);
            })
            ->when(!empty($params), function ($query) use ($params) {
                if (empty($params['contract_date_from'])) {
                    $query->when(!empty($params['period_date_from']) && !empty($params['period_date_to']), function ($query) use ($params) {
                        $query->whereBetween('act.act_dateposted', [$params['period_date_from'], $params['period_date_to']]);
                    });
                } else {
                    $query->when(!empty($params['contract_date_from']) && !empty($params['contract_date_to']), function ($query) use ($params) {
                        $query->whereBetween('act.act_dateposted', [$params['contract_date_from'], $params['contract_date_to']]);
                    });
                }
            })
            ->when(!empty($params['claimnum']), function ($query) use ($params) {
                $query->where('cl.cl_claimnum', $params['claimnum']);
            })
            ->when(!empty($params['policynum']), function ($query) use ($params) {
                $query->where('cl.cl_policynumber', $params['policynum']);
            })
            ->when(!empty($params['lastname']), function ($q) use ($params) {
                $q->where('c.ch_lastname', 'like', '%' . $params['lastname'] . '%');
            })
            ->when(!empty($params['firstname']), function ($q) use ($params) {
                $q->where('c.ch_firstname', 'like', '%' . $params['firstname'] . '%');
            })
            ->when(!empty($params['middlename']), function ($query) use ($params) {
                $query->where('c.ch_middlename', $params['middlename']);
            })
            ->orderBy('c.ch_name', 'asc');

        return $result->paginate($perPage);
    }

    private function applyCholderAccountFilters($query, $params, $authUser)
    {
        if ($authUser?->hasRole('broker')) {
            $agentAccounts = (new SqlDatabase(Server::HMS))
                ->getAccountsOfAgent($authUser->userDetail?->agent_code ?? null);
            $query->whereIn('c.ch_accountid', $agentAccounts);
        }

        if ($authUser?->hasRole('account_branch_admin')) {
            $firstAccount = $authUser->userAccounts->first();
            $query->where('c.ch_accountid', $firstAccount?->account_code ?? null);
            if (!empty($firstAccount?->branch_code)) {
                $query->where('c.ch_branch_code', $firstAccount->branch_code);
            }
        }

        if ($authUser?->hasRole('group_account_admin')) {
            $userAccounts = $authUser->userAccounts;
            if ($userAccounts->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($userAccounts) {
                    foreach ($userAccounts as $ua) {
                        $q->orWhere(function ($sub) use ($ua) {
                            $sub->where('c.ch_accountid', $ua->account_code);
                            if (!empty($ua->branch_code)) {
                                $sub->where('c.ch_branch_code', $ua->branch_code);
                            }
                        });
                    }
                });
            }
        }

        return $query;
    }

    /**
     * Applies account, branch, broker, and billing-date filters on a Claims query (alias "c").
     */
    private function applyClaimsPolicyFilters($query, array $params, $authUser): string
    {
        $accountCode = $params['account_code'] ?? null;

        if ($authUser?->hasRole('account_branch_admin')) {
            $firstAccount = $authUser->userAccounts->first();
            if (!empty($firstAccount?->account_code)) {
                $accountCode = $firstAccount->account_code;
            }
        }

        if ($authUser?->hasRole('group_account_admin')) {
            $userAccounts = $authUser->userAccounts;
            if ($userAccounts->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($userAccounts) {
                    foreach ($userAccounts as $ua) {
                        if (!empty($ua->account_code)) {
                            $q->orWhereRaw('SUBSTRING(c.cl_policynumber, 1, 11) = ?', [$ua->account_code]);
                        }
                    }
                });
                $accountCode = $userAccounts->first()?->account_code ?? '';
            }
            if (!empty($params['billing_date_from'])) {
                $query->where('c.cl_processdate', '>=', Carbon::parse($params['billing_date_from'])->startOfDay());
            }
            if (!empty($params['billing_date_to'])) {
                $query->where('c.cl_processdate', '<=', Carbon::parse($params['billing_date_to'])->endOfDay());
            }
            return $accountCode ?? '';
        }

        if (!empty($accountCode)) {
            $query->whereRaw('SUBSTRING(c.cl_policynumber, 1, 11) = ?', [$accountCode]);
        }

        if ($authUser?->hasRole('broker')) {
            $agentAccounts = (new SqlDatabase(Server::HMS))
                ->getAccountsOfAgent($authUser->userDetail?->agent_code ?? null);

            if ($agentAccounts->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn(DB::raw('SUBSTRING(c.cl_policynumber, 1, 11)'), $agentAccounts);
            }
        }

        $branchCode = $params['branch_code'] ?? null;

        if ($authUser?->hasRole('account_branch_admin')) {
            $firstAccount = $authUser->userAccounts->first();
            if (!empty($firstAccount?->branch_code)) {
                $branchCode = $firstAccount->branch_code;
            }
        }

        if (!empty($branchCode) && !empty($accountCode)) {
            $query->whereExists(function ($sub) use ($accountCode, $branchCode) {
                $sub->selectRaw('1')
                    ->from('Cholders as ch')
                    ->where('ch.ch_accountid', $accountCode)
                    ->where('ch.ch_branch_code', $branchCode);
            });
        }

        if (!empty($params['billing_date_from'])) {
            $query->where(
                'c.cl_processdate',
                '>=',
                Carbon::parse($params['billing_date_from'])->startOfDay()
            );
        }

        if (!empty($params['billing_date_to'])) {
            $query->where(
                'c.cl_processdate',
                '<=',
                Carbon::parse($params['billing_date_to'])->endOfDay()
            );
        }

        return $accountCode ?? '';
    }

    public function getClaimsReceivingDetailsByParams($params)
    {
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $authUser = auth()->user();
        $selectedRefs = $params['billing_refs'] ?? null;

        if (is_string($selectedRefs)) {
            $selectedRefs = array_filter(explode(',', $selectedRefs));
        } elseif (!is_array($selectedRefs)) {
            $selectedRefs = $selectedRefs ? [$selectedRefs] : [];
        }
        $selectedRefs = array_filter($selectedRefs);

        $latestClaims = $this->db
            ->table('Claims as c')
            ->select([
                'c.cl_batchnumber',
                DB::raw('MAX(c.cl_processdate) as cl_processdate'),
            ]);

        $accountCode = $this->applyClaimsPolicyFilters($latestClaims, $params, $authUser);
        $latestClaims->groupBy('c.cl_batchnumber');

        if (
            empty($accountCode)
            && !$authUser?->hasRole('broker')
            && !$authUser?->hasRole('account_branch_admin')
            && !$authUser?->hasRole('group_account_admin')
        ) {
            return $this->db->table('Claims_Receiving')->whereRaw('1 = 0')->simplePaginate($perPage);
        }

        $query = $this->db
            ->table('Claims_Receiving as cr')
            ->joinSub($latestClaims, 'latest', function ($join) {
                $join->on('cr.cr_batchnum', '=', 'latest.cl_batchnumber');
            })
            ->join('Claims as c', function ($join) {
                $join->on('c.cl_batchnumber', '=', 'latest.cl_batchnumber')
                    ->on('c.cl_processdate', '=', 'latest.cl_processdate');
            })
            ->select([
                'cr.cr_batchnum as ref_id',
                'cr.cr_amount as amount',
                'c.cl_processdate as date_posted',
                DB::raw('MAX(c.cl_claimnum) as cl_claimnum'),
            ])
            ->groupBy('cr.cr_batchnum', 'cr.cr_amount', 'c.cl_processdate')
            ->when(isset($params['name']) && !empty($params['name']), function ($query) use ($params, $selectedRefs) {
                $query->where(function ($nameQuery) use ($params, $selectedRefs) {
                    $nameQuery->where('cr.cr_batchnum', 'like', '%' . $params['name'] . '%');
                    if (!empty($selectedRefs)) {
                        $nameQuery->orWhereIn('cr.cr_batchnum', $selectedRefs);
                    }
                });
            })
            ->orderByDesc('date_posted')
            ->orderBy('ref_id');

        $paginated = $query->paginate($perPage);

        // Sort the current page's items so selected refs appear first.
        // Done in PHP rather than via orderByRaw bindings to avoid SQL Server's
        // binding-count mismatch on the paginator's COUNT sub-query.
        if (!empty($selectedRefs)) {
            $selectedSet = array_flip($selectedRefs);
            $paginated->setCollection(
                $paginated->getCollection()
                    ->sortBy(fn($item) => isset($selectedSet[$item->ref_id]) ? 0 : 1)
                    ->values()
            );
        }

        return $paginated;
    }

    public function getClaimDetailsByParams($params)
    {
        $authUser = auth()->user();
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $selectedRefs = $params['billing_refs'] ?? null;

        // Handle both single string and array of selected refs
        if (is_string($selectedRefs)) {
            $selectedRefs = array_filter(explode(',', $selectedRefs));
        } elseif (!is_array($selectedRefs)) {
            $selectedRefs = $selectedRefs ? [$selectedRefs] : [];
        }
        $selectedRefs = array_filter($selectedRefs);
        $query = $this->db
            ->table('cholders as c')
            ->leftJoin('Accounts as a', 'c.ch_accountid', '=', 'a.ac_code')
            ->leftJoin('Claims as cl', 'c.ch_policynum', '=', 'cl.cl_policynumber')
            ->leftJoin('Acctg as ac', 'cl.cl_batchnumber', '=', 'ac.act_batchnum')
            ->select(
                'a.ac_code',
                'a.ac_name',
                'c.ch_accountid',
                'c.ch_policynum',
                'c.ch_name',
                'ac.act_batchnum as ref_id',
                'cl.cl_availmentdate',
                'ac.act_dateposted as date_posted',
                'cl.cl_claimnum',
                'cl.cl_amount as amount',
                'cl.cl_processdate'
            );

        $query = $this->applyCholderAccountFilters($query, $params, $authUser);

        $result = $query
            ->when(isset($params['name']) && !empty($params['name']), function ($query) use ($params, $selectedRefs) {
                $query->where(function ($nameQuery) use ($params, $selectedRefs) {
                    $nameQuery->where('ac.act_batchnum', 'like', '%' . $params['name'] . '%');
                    // Include selected refs in results
                    if (!empty($selectedRefs)) {
                        $nameQuery->orWhereIn('ac.act_batchnum', $selectedRefs);
                    }
                });
            })
            ->when(!empty($params['billing_date_from']), function ($query) use ($params) {
                $query->where('cl.cl_processdate', '>=', Carbon::parse($params['billing_date_from'])->startOfDay());
            })
            ->when(!empty($params['billing_date_to']), function ($query) use ($params) {
                $query->where('cl.cl_processdate', '<=', Carbon::parse($params['billing_date_to'])->endOfDay());
            })
            ->when(!empty($params['claimnum']), function ($query) use ($params) {
                $query->where('cl.cl_claimnum', $params['claimnum']);
            })
            ->when(!empty($selectedRefs), function ($query) use ($selectedRefs) {
                $placeholders = implode(',', array_fill(0, count($selectedRefs), '?'));
                $query->orderByRaw(
                    "CASE WHEN ac.act_batchnum IN ({$placeholders}) THEN 0 ELSE 1 END",
                    array_values($selectedRefs)
                );
            })
            ->when(!empty($params['policynum']), function ($query) use ($params) {
                $query->where('c.ch_policynum', $params['policynum']);
            })
            ->when(empty($params['order_by']), function ($query) {
                $query->orderBy('cl.cl_processdate', OrderType::DESC);
            })
            ->when(!empty($params['order_by']), function ($query) use ($params) {
                $allowedColumns = ['cl.cl_processdate', 'cl.cl_claimnum', 'cl.cl_amount', 'ac.act_batchnum', 'ac.act_dateposted'];
                $allowedDirs = ['asc', 'desc'];
                $col = in_array($params['order_by'], $allowedColumns, true) ? $params['order_by'] : 'cl.cl_processdate';
                $dir = in_array(strtolower($params['order_dir'] ?? 'asc'), $allowedDirs, true) ? strtolower($params['order_dir']) : 'asc';
                $query->orderBy($col, $dir);
            })
            ->where('ac.act_batchnum', '!=', '');

        return $result->paginate($perPage);
    }

    /**
     * Searches members (cardholders) across policynum, batch number, claimnum,
     * lastname, firstname, account code, and company name.
     * Returns one row per claim so claimnum is available for file attachment lookup.
     *
     * @param array $params
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getMembersByParams($params)
    {
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $authUser = auth()->user();

        $query = $this->db
            ->table('cholders as c')
            ->leftJoin('Claims as cl', 'c.ch_policynum', '=', 'cl.cl_policynumber')
            ->leftJoin('Accounts as a', 'c.ch_accountid', '=', 'a.ac_code')
            ->select([
                'c.ch_id',
                'c.ch_policynum',
                'c.ch_firstname',
                'c.ch_lastname',
                'c.ch_middlename',
                'c.ch_suffix',
                'c.ch_accountid',
                'a.ac_name',
                'cl.cl_claimnum as claimnum',
                'cl.cl_batchnumber as batch_number',
                'cl.cl_processdate as process_date',
            ]);

        if ($authUser?->hasRole('broker')) {
            $agentAccounts = (new SqlDatabase(Server::HMS))
                ->getAccountsOfAgent($authUser->userDetail?->agent_code ?? null);
            $query->whereIn('c.ch_accountid', $agentAccounts);
        }

        if ($authUser?->hasRole('account_branch_admin')) {
            $firstAccount = $authUser->userAccounts->first();
            $query->where('c.ch_accountid', $firstAccount?->account_code ?? null);
            if (!empty($firstAccount?->branch_code)) {
                $query->where('c.ch_branch_code', $firstAccount->branch_code);
            }
        }

        if ($authUser?->hasRole('group_account_admin')) {
            $userAccounts = $authUser->userAccounts;
            if ($userAccounts->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($userAccounts) {
                    foreach ($userAccounts as $ua) {
                        $q->orWhere(function ($sub) use ($ua) {
                            $sub->where('c.ch_accountid', $ua->account_code);
                            if (!empty($ua->branch_code)) {
                                $sub->where('c.ch_branch_code', $ua->branch_code);
                            }
                        });
                    }
                });
            }
        }

        $query
            ->when(!empty($params['policynum']), function ($q) use ($params) {
                $q->where('c.ch_policynum', $params['policynum']);
            })
            ->when(!empty($params['batch_number']), function ($q) use ($params) {
                $q->where('cl.cl_batchnumber', $params['batch_number']);
            })
            ->when(!empty($params['claimnum']), function ($q) use ($params) {
                $q->where('cl.cl_claimnum', 'like', '%' . $params['claimnum'] . '%');
            })
            ->when(!empty($params['lastname']), function ($q) use ($params) {
                $q->where('c.ch_lastname', 'like', '%' . $params['lastname'] . '%');
            })
            ->when(!empty($params['firstname']), function ($q) use ($params) {
                $q->where('c.ch_firstname', 'like', '%' . $params['firstname'] . '%');
            })
            ->when(!empty($params['account_code']), function ($q) use ($params) {
                $q->where('c.ch_accountid', $params['account_code']);
            })
            ->when(!empty($params['company_name']), function ($q) use ($params) {
                $q->where('a.ac_name', 'like', '%' . $params['company_name'] . '%');
            })
            ->orderBy('cl.cl_processdate', OrderType::DESC)
            ->orderBy('c.ch_lastname');

        return $query->paginate($perPage);
    }

    public function getMdaDetailsByParams($params)
    {
        // Pagination
        $authUser = auth()->user();
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $selectedRefs = $params['billing_refs'] ?? null;

        // Handle both single string and array of selected refs
        if (is_string($selectedRefs)) {
            $selectedRefs = array_filter(explode(',', $selectedRefs));
        } elseif (!is_array($selectedRefs)) {
            $selectedRefs = $selectedRefs ? [$selectedRefs] : [];
        }
        $selectedRefs = array_filter($selectedRefs);
        $result = $this->db
            ->table('Billing as a')
            ->select([
                'a.bl_refid as ref_id',
                'a.bl_type',
                'a.bl_claimnum',
                'a.bl_policynum',
                'a.bl_balance as amount',
                'a.bl_name',
                'a.bl_dateposted as date_posted',
                'a.bl_workstatus',
                'c.blp_tobebilledby',
                'e.ac_name'
            ])
            ->leftJoin('Billing_Process as c', 'a.bl_refid', '=', 'c.blp_refid')
            ->leftJoin('Accounts as e', function ($join) {
                $join->on(DB::raw('SUBSTRING(a.bl_policynum,1,11)'), '=', 'e.ac_code');
            })
            ->where('e.ac_code', $params['account_code'])
            ->when(isset($params['name']) && !empty($params['name']), function ($query) use ($params, $selectedRefs) {
                $query->where(function ($nameQuery) use ($params, $selectedRefs) {
                    $nameQuery->where('a.bl_refid', 'like', '%' . $params['name'] . '%');
                    // Include selected refs in results
                    if (!empty($selectedRefs)) {
                        $nameQuery->orWhereIn('a.bl_refid', $selectedRefs);
                    }
                });
            })
            ->when(!empty($params['account_type']) && ($params['account_type'] != AccountType::HMO), function ($query) {
                $query->whereNotIn('a.bl_refid', function ($query) {
                    $query->select('a.bl_refid')
                        ->from('Billing as a')
                        ->leftJoin('Billing_Process as b', 'a.bl_refid', '=', 'b.blp_refid')
                        ->whereIn('a.bl_workstatus', ['FB', 'PX'])
                        ->where('a.bl_type', 'MEDCOLL')
                        ->where('b.blp_tobebilledby', 'BILLING');
                });
            })
            ->when(isset($params['billing_date_from']) && !empty($params['billing_date_from']), function ($query) use ($params) {
                $query->where('a.bl_dateposted', '>=', Carbon::parse($params['billing_date_from'])->startOfDay());
            })
            ->when(isset($params['billing_date_to']) && !empty($params['billing_date_to']), function ($query) use ($params) {
                $query->where('a.bl_dateposted', '<=', Carbon::parse($params['billing_date_to'])->endOfDay());
            })
            ->orderBy('a.bl_dateposted', 'desc');

        $paginated = $result->paginate($perPage);

        // Sort the current page's items so selected refs appear first.
        // Done in PHP rather than via orderByRaw bindings to avoid SQL Server's
        // binding-count mismatch on the paginator's COUNT sub-query.
        if (!empty($selectedRefs)) {
            $selectedSet = array_flip($selectedRefs);
            $paginated->setCollection(
                $paginated->getCollection()
                    ->sortBy(fn($item) => isset($selectedSet[$item->ref_id]) ? 0 : 1)
                    ->values()
            );
        }

        return $paginated;
    }

    /**
     * Retrieves branches based on the specified type.
     *
     * @return \Illuminate\Pagination\Paginator
     */
    public function getBranchesByParams($params)
    {
        // Pagination
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $selectedCode = $params['selected_code'] ?? null;
        $result = $this->db
            ->table('Branches')
            ->select('br_branch_name', 'br_ac_code', 'br_code')
            ->when(isset($params['account_code']), function ($query) use ($params) {
                $query->where('br_ac_code', $params['account_code']);
            })
            ->when(isset($params['name']) && $params['name'] !== '', function ($query) use ($params, $selectedCode) {
                $query->where(function ($nameQuery) use ($params, $selectedCode) {
                    $nameQuery->where('br_branch_name', 'like', '%' . $params['name'] . '%');
                    if (!empty($selectedCode)) {
                        $nameQuery->orWhere('br_code', $selectedCode);
                    }
                });
            })
            ->when(!empty($selectedCode), function ($query) use ($selectedCode) {
                $query->orderByRaw("CASE WHEN br_code = ? THEN 0 ELSE 1 END", [$selectedCode]);
            })
            ->orderBy('br_branch_name');

        return $result->paginate($perPage);
    }

    /**
     * Untags a SOA record.
     *
     * @param array $params
     * @return void
     */
    public function untagSoa($soa, $params)
    {
        $username = auth()->user()->username;
        $userIp = request()->ip();
        $now = now();

        try {
            // Update SOA record to untag
            $this->db
                ->table('Upload')
                ->where('up_id', $params['id'])
                ->update([
                    'up_status_date' => null,//null
                    'up_status' => null,//0
                    'up_balance' => null,//null
                ]);
            //save history
            $this->saveHistory([
                'soanum' => $params['soanum'],
                'changes' => 'Retract Paid Status',
                'username' => $username,
                'datetime' => $now,
                'ip' => $userIp,
            ]);
            //save remarks
            $this->saveRemarks([
                ...$params,
                'datetime' => $now,
                'username' => $username,
                'accode' => $soa->up_accode,
                'macode' => $soa->up_macode,
                'branch' => $soa->up_branch,
                'rem_isVC' => 1,
            ]);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Saves changes to a SOA record.
     *
     * @param array $params
     * @return void
     */
    public function saveSoa($params)
    {
        $username = auth()->user()->username;
        $userIp = request()->ip();
        $now = now();

        try {
            if (isset($params['id'])) {
                // Update SOA record to untag
                $this->db
                    ->table('Upload')
                    ->where('up_id', $params['id'])
                    ->update([
                        'up_amount' => $params['amount_due'],
                    ]);
                $changes = 'Updated a SOA record';
            } else {
                // Save SOA record
                $this->db
                    ->table('Upload')
                    ->save($params);
                $changes = 'Created a SOA record';
            }

            //save history
            $this->saveHistory([
                'soanum' => $params['soanum'],
                'changes' => $changes,
                'username' => $username,
                'datetime' => $now,
                'ip' => $userIp,
            ]);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Inserts a history record into the SOA_History_of_Changes table.
     *
     * @param array $params
     * @return void
     */
    public function saveHistory($params)
    {
        //Insert history
        $this->db
            ->table('SOA_History_of_Changes')
            ->insert([
                'soa_hc_ref' => $params['soanum'],
                'soa_hc_changes' => $params['changes'],
                'soa_hc_by' => $params['username'],
                'soa_hc_datetime' => $params['datetime'],
                'soa_hc_ip' => $params['ip'],
            ]);
    }

    /**
     * Inserts a remarks record into the remarks table.
     *
     * @param array $params an associative array containing the following keys:
     *   - reason: a string describing the reason for the remark
     *   - soanum: a string representing the SOA number
     *   - datetime: a datetime string representing the date and time of the remark
     *   - username: a string representing the username of the user making the remark
     *   - accode: a string representing the account code
     *   - macode: a string representing the macro code
     *   - branch: a string representing the branch
     *   - isVC: an integer representing whether the remark is for a VC (1) or not (0)
     *
     * @return void
     */
    public function saveRemarks($params)
    {
        //Insert remarks
        $this->db
            ->table('remarks')
            ->insert([
                'rem_remark' => $params['reason'],
                'rem_refid' => $params['soanum'],
                'rem_date' => $params['datetime'],
                'rem_by' => $params['username'],
                'rem_accode' => $params['accode'],
                'rem_macode' => $params['macode'],
                'rem_branch' => $params['branch'],
                'rem_isVC' => 1,
            ]);
    }
}
