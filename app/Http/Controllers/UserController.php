<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const MODULE_PERMISSIONS = [
        'escenarios' => [
            'escenarios.leer', 'escenarios.crear', 'escenarios.actualizar',
            'escenarios.eliminar', 'escenarios.invitar', 'escenarios.versionar',
            'archivos.leer', 'archivos.actualizar',
        ],
        'emociones' => ['emociones.leer', 'emociones.cargar', 'emociones.invitar'],
    ];

    public function data()
    {
        return response()->json(User::with('roles:id,name')->latest()->get()->map(function (User $user): array {
            $permissionNames = $user->getAllPermissions()->pluck('name');

            return [
                ...$user->only(['id', 'name', 'email', 'is_active', 'created_at']),
                'roles' => $user->roles->map->only(['id', 'name'])->values(),
                'modules' => $user->hasRole('admin')
                    ? array_keys(self::MODULE_PERMISSIONS)
                    : collect(self::MODULE_PERMISSIONS)
                        ->filter(fn (array $permissions): bool => $permissionNames->contains($permissions[0]))
                        ->keys()
                        ->values(),
            ];
        }));
    }

    public function index()
    {
        return view('app');
    }

    public function list()
    {
        return view('app');
    }

    public function show(User $user)
    {
        return view('app');
    }

    public function store(Request $request)
    {
        $validated = $this->validateUser($request, null, true);
        $modules = $validated['modules'] ?? [];
        $role = $validated['role'];
        unset($validated['modules'], $validated['role']);

        $user = User::create($validated);
        $this->syncAccess($user, $role, $modules);

        return response()->json([
            'message' => 'Usuario registrado y accesos asignados correctamente.',
            'data' => $user->load('roles:id,name'),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $this->validateUser($request, $user, false);
        $modules = $validated['modules'] ?? [];
        $role = $validated['role'];

        if ($user->hasRole('admin') && ($role !== 'admin' || ! $validated['is_active'])) {
            abort_if(
                User::role('admin')->where('is_active', true)->count() <= 1,
                422,
                'Debe permanecer al menos un administrador activo.'
            );
        }

        unset($validated['modules'], $validated['role']);
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);
        $this->syncAccess($user, $role, $modules);

        return response()->json([
            'message' => 'Usuario y accesos actualizados correctamente.',
            'data' => $user->fresh()->load('roles:id,name'),
        ]);
    }

    public function destroy(User $user)
    {
        return response()->json(['message' => 'Operación aún no implementada.'], 501);
    }

    private function validateUser(Request $request, ?User $user, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'usuario'])],
            'modules' => ['nullable', 'array'],
            'modules.*' => [Rule::in(array_keys(self::MODULE_PERMISSIONS))],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function syncAccess(User $user, string $role, array $modules): void
    {
        Role::findOrCreate($role, 'web');
        $user->syncRoles($role);

        if ($role === 'admin') {
            $user->syncPermissions([]);

            return;
        }

        $permissions = collect($modules)
            ->flatMap(fn (string $module): array => self::MODULE_PERMISSIONS[$module] ?? [])
            ->unique()
            ->values()
            ->all();

        $user->syncPermissions($permissions);
    }
}
