<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     *
     * Manually removes child rows that aren't covered by cascading FK deletes
     * (e.g. categories whose FK was created without ON DELETE CASCADE in an
     * older migration, or any table that references users without cascade).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        // Delete all user-owned data in dependency order before removing the user.
        // This guards against FK constraints that aren't set to CASCADE in MySQL.
        DB::transaction(function () use ($user) {
            $userId = $user->id;

            // Transactions (soft-deleted rows still exist; force-delete them)
            DB::table('transactions')->where('user_id', $userId)->delete();

            // Categories (FK to users without cascade on some installations)
            DB::table('categories')->where('user_id', $userId)->delete();

            // Accounts
            DB::table('accounts')->where('user_id', $userId)->delete();

            // Loans, client funds, transfers, budgets
            DB::table('loans')->where('user_id', $userId)->delete();
            DB::table('client_funds')->where('user_id', $userId)->delete();
            DB::table('transfers')->where('user_id', $userId)->delete();
            DB::table('budgets')->where('user_id', $userId)->delete();

            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
