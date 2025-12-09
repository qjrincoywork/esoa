<?php

namespace App\Helpers;

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
}
