<?php

namespace App\Helpers;

use App\Enums\AccountType;
use Illuminate\Support\Facades\DB;

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
            // ->limit(1)
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
        $result = $this->db
            ->table('Accounts')
            ->select('ac_name', 'ac_code', 'ac_ma_code')
            ->when(isset($params['type']), function ($query) use ($params) {
                if (AccountType::HMO === $params['type']) {
                    $query->where('ac_accttype', $params['type']);
                } else {
                    $query->where('ac_accttype', '!=', AccountType::HMO);
                }
            })
            ->when(isset($params['name']), function ($query) use ($params) {
                $query->where('ac_name', 'like', '%' . $params['name'] . '%');
            })
            ->where(function ($q) {
                $q->where('ac_code', 'not like', 'IN%')
                    ->where('ac_code', 'not like', 'FM%')
                    ->where('ac_code', 'not like', 'GR%');
            })
            ->where('ac_status', 'A') // Active accounts only
            ->groupBy('ac_name', 'ac_code', 'ac_ma_code')
            ->orderBy('ac_name');

        return $result->paginate($perPage);
    }

    /**
     * Retrieves branches based on the specified type.
     *
     * @param string $type The type of branches to retrieve
     * @return \Illuminate\Pagination\Paginator
     */
    public function getBranchesByParams($params)
    {
        // Pagination
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $result = $this->db
            ->table('Branches')
            ->select('br_branch_name', 'br_ac_code', 'br_code')
            ->when(isset($params['account_code']), function ($query) use ($params) {
                $query->where('br_ac_code', $params['account_code']);
            })
            ->when(isset($params['name']), function ($query) use ($params) {
                $query->where('br_branch_name', 'like', '%' . $params['name'] . '%');
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
