<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('escenarios_users')->where('access_level', 'collaborator')->update(['access_level' => 'editor']);
    }

    public function down(): void
    {
        DB::table('escenarios_users')->where('access_level', 'editor')->update(['access_level' => 'collaborator']);
    }
};
