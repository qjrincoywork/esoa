<?php

namespace App\Http\Controllers;

use App\Enums\ConcernType;
use App\Enums\TicketStatus;
use App\Models\Concern;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConcernController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $concerns = Concern::with(['user', 'soa'])
            ->when($request->search, function ($query) use ($request) {
                $query->where('title', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
            })
            ->paginate(10);

        return Inertia::render('concerns/Index', [
            'concerns' => $concerns,
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
                'concern_types' => ConcernType::list(),
                'ticket_statuses' => TicketStatus::list(),
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'billing_invoice' => 'nullable|string',
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'required|string',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
        ]);

        $data = $request->all();
        $data['user_id'] = auth()->id();
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('concerns', 'public');
        }

        Concern::create($data);

        return response()->json(['message' => 'Concern created successfully.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Concern $concern)
    {
        $concern->load(['user', 'soa']);

        return Inertia::render('concerns/Show', [
            'concern' => $concern,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Concern $concern)
    {
        $concern->load(['user', 'soa']);

        return Inertia::render('concerns/Edit', [
            'concern' => $concern,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $request->validate([
            'billing_invoice' => 'nullable|string',
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'required|string',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
        ]);

        $concern = Concern::findOrFail($request->id);

        $data = $request->all();
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('concerns', 'public');
        }

        $concern->update($data);

        return response()->json(['message' => 'Concern updated successfully.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $concern = Concern::findOrFail($request->id);
        $concern->delete();

        return response()->json(['message' => 'Concern deleted successfully.']);
    }
}
