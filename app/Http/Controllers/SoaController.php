<?php

namespace App\Http\Controllers;

use App\Helpers\CustomResponse;
use App\Helpers\SqlDatabase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Soa\ListRequest;
use App\Http\Resources\CommonResource;
use App\Http\Resources\SoaResource;
use App\Models\{Account, Citizenship, CivilStatus, Contact, Department, Gender, MainAccount, Position, Suffix, User };
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SoaController extends Controller
{
    /**
     * SqlDatabase instance.
     *
     * @var SqlDatabase
     */
    protected $sqlDatabase;


    /**
     * Constructor
     *
     * @param SqlDatabase $sqlDatabase
     *
     * @return void
     */
    public function __construct()
    {
        $this->sqlDatabase = SqlDatabase::class;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ListRequest $request)
    {
        $soas = (new $this->sqlDatabase('soa'))->getSoas($request->validated());

        return Inertia::render('soas/Index', [
            'soas' => new CommonResource(SoaResource::collection($soas))
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $soa = (new $this->sqlDatabase('soa'))->getSoa($id);

        return Inertia::render('soas/Index', [
            'soa' => SoaResource::make($soa)
        ]);
    }
}
