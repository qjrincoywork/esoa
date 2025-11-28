<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Http\Requests\Navigation\ListRequest;
use App\Http\Resources\NavigationResource;
use App\Models\Navigation;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NavigationController extends Controller
{
    /**
     * Navigation model instance.
     *
     * @var Navigation
     */
    protected $navigation;

    /**
     * NavigationController constructor.
     *
     * @param Navigation $navigation
     *
     * @return void
     */
    public function __construct(Navigation $navigation)
    {
        $this->navigation = $navigation;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ListRequest $request)
    {
        $navs = $this->navigation->getNavigations($request->validated())->toArray();

        dd(NavigationResource::make($navs));
        return Inertia::render('navigations/Index', [
            'navigation_list' => $navs
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'statuses' => Status::list(),
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Navigation $navigation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Navigation $navigation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Navigation $navigation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Navigation $navigation)
    {
        //
    }
}
