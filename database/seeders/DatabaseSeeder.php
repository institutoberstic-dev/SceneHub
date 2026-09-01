<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $admin = User::updateOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Administrador',
            'password' => Hash::make('password'),
        ]);

        $admin->syncRoles('admin');

        $owner = User::updateOrCreate([
            'email' => 'owner@example.com',
        ], [
            'name' => 'Cliente propietario',
            'password' => Hash::make('password'),
        ]);

        $owner->syncRoles('cliente');

        $supervisor = User::updateOrCreate([
            'email' => 'supervisor@example.com',
        ], [
            'name' => 'Supervisor',
            'password' => Hash::make('password'),
        ]);

        $supervisor->syncRoles('cliente');

        $collaborator = User::updateOrCreate([
            'email' => 'collaborator@example.com',
        ], [
            'name' => 'Colaborador',
            'password' => Hash::make('password'),
        ]);

        $collaborator->syncRoles('cliente');
    }
}
