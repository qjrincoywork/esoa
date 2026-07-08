<?php

namespace App\Http\Controllers\Settings;

use App\Helpers\CustomResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log};
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the authenticated user's own profile information.
     * Always acts on $request->user() — never accepts an id from the body.
     */
    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();

        DB::beginTransaction();

        try {
            $user->fill($request->validated());

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            DB::commit();

            return to_route('profile.edit');
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson() || $request->ajax()) {
                return CustomResponse::serverError($e, 'ProfileController::update');
            }

            return back()->withErrors(['error' => 'Update failed. Please try again.']);
        }
    }

    /**
     * Delete the authenticated user's own account after password confirmation.
     *
     * Requires the current password, then logs the user out, deletes the account,
     * invalidates the session and regenerates the CSRF token, and redirects to the
     * site root.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
