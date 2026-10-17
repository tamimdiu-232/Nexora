<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit() { return view('profile.edit', ['user' => auth()->user()]); }

    public function update(Request $request)
    {
        $u = $request->user();
        $v = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($u->id)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);
        $u->fill(['name' => $v['name'], 'email' => $v['email']]);
        if (!empty($v['password'])) { $u->password = $v['password']; AuditLogger::log('auth.password_changed'); }
        $u->save();
        AuditLogger::log('profile.updated');
        return back()->with('success', 'Profile updated.');
    }
}
