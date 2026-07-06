<?php

namespace App\Http\Controllers;

use App\Http\Requests\Setup\StoreSetupRequest;
use App\Models\User;
use App\Services\InstallationService;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function create(): View
    {
        return view('setup.create');
    }

    public function store(StoreSetupRequest $request, InstallationService $installation): RedirectResponse
    {
        $user = DB::transaction(function () use ($request, $installation): User {
            $data = $request->validated();

            $role = Role::firstOrCreate(
                ['slug' => 'super-admin'],
                ['name' => 'Super Admin']
            );

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->assignRole($role);
            $installation->markInstalled();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/dashboard')
            ->with('success', __('Initial administrator account created.'));
    }
}
