<?php

namespace App\Http\Controllers\Garda;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function profile()
    {
        $user = Auth::user();
        return view('garda.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
        ], [
            'username.unique' => 'Username sudah terpakai.',
        ]);

        if ($request->filled('new_password')) {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            if (!Hash::check($request->current_password, $user->password)) {
                return back()->with('error', 'Password saat ini salah.');
            }
        }

        DB::transaction(function () use ($request, $user) {
            $oldUsername = $user->username;
            $newUsername = $request->username;

            if ($oldUsername !== $newUsername) {
                \App\Models\Kompi::where('garda_id', $oldUsername)->update(['garda_id' => null]);
            }

            $updateData = [
                'full_name' => $request->full_name,
                'email' => $request->email,
                'username' => $newUsername,
            ];

            if ($request->filled('new_password')) {
                $updateData['password'] = Hash::make($request->new_password);
            }

            $user->update($updateData);

            if ($oldUsername !== $newUsername) {
                \App\Models\Kompi::where('nama', $user->assigned_kompi)->update(['garda_id' => $newUsername]);
            }
        });

        return back()->with('success', 'Profil berhasil diperbarui.');
    }
}
