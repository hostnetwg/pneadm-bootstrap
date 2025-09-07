<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;

class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Sprawdź uprawnienia
        if (!auth()->user()->hasPermission('users.view')) {
            abort(403, 'Brak uprawnień do przeglądania użytkowników.');
        }

        $users = User::with('role')->orderBy('id', 'asc')->paginate(15);
        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Sprawdź uprawnienia
        if (!auth()->user()->hasPermission('users.create')) {
            abort(403, 'Brak uprawnień do tworzenia użytkowników.');
        }

        $roles = Role::where('level', '<=', auth()->user()->role->level)->get();
        return view('admin.users.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Sprawdź uprawnienia
        if (!auth()->user()->hasPermission('users.create')) {
            abort(403, 'Brak uprawnień do tworzenia użytkowników.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['boolean'],
        ]);

        // Sprawdź czy można przypisać daną rolę
        $selectedRole = Role::findOrFail($request->role_id);
        if ($selectedRole->level > auth()->user()->role->level) {
            return redirect()->back()
                ->with('error', 'Nie możesz przypisać roli o wyższym poziomie uprawnień.')
                ->withInput();
        }

        // Sprawdź czy nie próbuje się ustawić statusu nieaktywnego dla superadministratora
        if ($selectedRole->name === 'super_admin' && !$request->boolean('is_active')) {
            return redirect()->back()
                ->with('error', 'Nie można ustawić statusu nieaktywnego dla Super Administratora.')
                ->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Użytkownik został utworzony pomyślnie.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        // Sprawdź uprawnienia
        if (!auth()->user()->hasPermission('users.view')) {
            abort(403, 'Brak uprawnień do przeglądania użytkowników.');
        }

        $user->load('role');
        return view('admin.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        // Sprawdź uprawnienia
        if (!auth()->user()->hasPermission('users.edit')) {
            abort(403, 'Brak uprawnień do edycji użytkowników.');
        }

        // Sprawdź czy można edytować tego użytkownika (tylko użytkownicy o niższym poziomie)
        if ($user->role && $user->role->level >= auth()->user()->role->level && $user->id !== auth()->id()) {
            abort(403, 'Nie możesz edytować użytkownika o równym lub wyższym poziomie uprawnień.');
        }

        $roles = Role::where('level', '<=', auth()->user()->role->level)->get();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        // Sprawdź uprawnienia
        if (!auth()->user()->hasPermission('users.edit')) {
            abort(403, 'Brak uprawnień do edycji użytkowników.');
        }

        // Sprawdź czy można edytować tego użytkownika
        if ($user->role && $user->role->level >= auth()->user()->role->level && $user->id !== auth()->id()) {
            abort(403, 'Nie możesz edytować użytkownika o równym lub wyższym poziomie uprawnień.');
        }

        // Sprawdź czy użytkownik nie próbuje zmienić własnego statusu
        if ($user->id === auth()->id() && $request->boolean('is_active') !== $user->is_active) {
            return redirect()->back()
                ->with('error', 'Nie możesz zmienić własnego statusu aktywności.')
                ->withInput();
        }

        // Sprawdź czy nie próbuje się ustawić statusu nieaktywnego dla superadministratora
        $selectedRole = Role::findOrFail($request->role_id);
        if ($selectedRole->name === 'super_admin' && !$request->boolean('is_active')) {
            return redirect()->back()
                ->with('error', 'Nie można ustawić statusu nieaktywnego dla Super Administratora.')
                ->withInput();
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['boolean'],
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'role_id' => $request->role_id,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Użytkownik został zaktualizowany pomyślnie.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // Sprawdź uprawnienia
        if (!auth()->user()->hasPermission('users.delete')) {
            abort(403, 'Brak uprawnień do usuwania użytkowników.');
        }

        // Zabezpieczenia przed usunięciem
        if ($user->id === auth()->id()) {
            return redirect()->back()
                ->with('error', 'Nie możesz usunąć własnego konta.');
        }

        // Sprawdź czy to ostatni Super Admin
        if ($user->isSuperAdmin()) {
            $superAdminCount = User::whereHas('role', function($query) {
                $query->where('name', 'super_admin');
            })->count();
            
            if ($superAdminCount <= 1) {
                return redirect()->back()
                    ->with('error', 'Nie można usunąć ostatniego Super Administratora.');
            }
        }

        // Sprawdź czy można usunąć użytkownika (tylko użytkownicy o niższym poziomie)
        if ($user->role && $user->role->level >= auth()->user()->role->level) {
            return redirect()->back()
                ->with('error', 'Nie możesz usunąć użytkownika o równym lub wyższym poziomie uprawnień.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Użytkownik został usunięty pomyślnie.');
    }

    /**
     * Toggle user active status
     */
    public function toggleStatus(User $user)
    {
        // Sprawdź uprawnienia
        if (!auth()->user()->hasPermission('users.edit')) {
            abort(403, 'Brak uprawnień do edycji użytkowników.');
        }

        // Sprawdź czy można edytować tego użytkownika
        if ($user->role && $user->role->level >= auth()->user()->role->level && $user->id !== auth()->id()) {
            abort(403, 'Nie możesz edytować użytkownika o równym lub wyższym poziomie uprawnień.');
        }

        // Zabezpieczenie przed dezaktywacją samego siebie
        if ($user->id === auth()->id()) {
            return redirect()->back()
                ->with('error', 'Nie możesz dezaktywować własnego konta.');
        }

        // Sprawdź czy użytkownik ma status większy niż 1 (manager, admin, super_admin)
        if ($user->role && $user->role->level > 1) {
            $user->update(['is_active' => !$user->is_active]);
            
            $status = $user->is_active ? 'aktywowany' : 'dezaktywowany';
            return redirect()->back()
                ->with('success', "Użytkownik {$user->name} został {$status} pomyślnie.");
        }

        return redirect()->back()
            ->with('error', 'Można dezaktywować tylko użytkowników o statusie wyższym niż podstawowy użytkownik.');
    }
}
