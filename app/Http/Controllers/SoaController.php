<?php

namespace App\Http\Controllers;

use App\Enums\UntagType;
use App\Helpers\CommonHelper;
use App\Helpers\CustomResponse;
use App\Helpers\SqlDatabase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Soa\{FileProxyRequest, ListRequest, UpdateRequest, UpdateTagRequest };
use App\Http\Resources\CommonResource;
use App\Http\Resources\SoaResource;
use App\Mail\NewSoaUploaded;
use App\Models\{Account, Citizenship, CivilStatus, Contact, Department, Gender, MainAccount, Position, Suffix, User };
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
     * Fetch a file from the given URL.
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function fileProxy(FileProxyRequest $request)
    {
        $fileUrl = env('VC_ADMIN_DOMAIN') . $request->get('url');

        if (!$fileUrl) {
            return CustomResponse::error('URL parameter is required', Response::HTTP_BAD_REQUEST);
        }

        try {
            $response = Http::withoutVerifying()->timeout(15)->get($fileUrl);
            $contentType = $response->header('Content-Type') ?? 'application/octet-stream';

            if ($response->successful()) {
                return response($response->body(), Response::HTTP_OK, [
                    'Content-Type' => $contentType,
                    'Access-Control-Allow-Origin' => '*',
                    'Content-Disposition' => 'inline',
                ]);
            } else {
                return CustomResponse::error('Failed to fetch PDF', $response->status());
            }
        } catch (\Exception $e) {
            return CustomResponse::error('An error occurred: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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

    /**
     * Edit the specified resource.
     */
    public function edit(Request $request, int $id)
    {
        $soa = (new $this->sqlDatabase('soa'))->getSoa($id);

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'soa' => SoaResource::make($soa)
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request)
    {
        $validated = $request->validated();

        DB::connection('soa')->beginTransaction();

        try {
            (new $this->sqlDatabase('soa'))->saveSoa($validated);

            // Commit transaction
            DB::connection('soa')->commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Soa Updated successfully', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::connection('soa')->rollBack();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                // Catch and handle any unexpected errors
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }

    /**
     * Untag the specified soa from a user.
     */
    public function untag(Request $request)
    {
        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'untag_types' => UntagType::list(),
            ]);
        }
    }

    /**
     * Update the tag of the specified soa from a user.
     */
    public function updateTag(UpdateTagRequest $request)
    {
        $validated = $request->validated();
        DB::connection('soa')->beginTransaction();

        try {
            switch ($validated['untag_type']) {
                case UntagType::USER_ERROR:
                    $validated['reason'] = __('esoa.reason.user_error');
                    break;
                case UntagType::CLIENT_ERROR:
                    $validated['reason'] = __('esoa.reason.client_error');
                    break;
                case UntagType::BOUNCED_RETURNED_CHECK:
                    $validated['reason'] = __('esoa.reason.bounced_returned_check');
                    break;
            }
            $soa = (new $this->sqlDatabase('soa'))->getSoa($validated['id']);

            // dd($soa);
            if (!$soa) {
                throw new \Exception('SOA record not found.');
            }

            // (new $this->sqlDatabase('soa'))->untagSoa($soa, $validated);
            Mail::to('quirjohnincoy.work@gmail.com')->send(new NewSoaUploaded($soa));

            // Commit transaction
            DB::connection('soa')->commit();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::ok('Retraction Completed', Response::HTTP_OK);
            }
        } catch (\Exception $e) {
            // Catch and handle any unexpected errors
            DB::connection('soa')->rollBack();

            // Return JSON for AJAX requests (no URL change)
            if ($request->wantsJson() || $request->ajax()) {
                // Catch and handle any unexpected errors
                return CustomResponse::error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }
}
