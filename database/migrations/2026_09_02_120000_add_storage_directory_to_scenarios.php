<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esceanarios', function (Blueprint $table) {
            $table->string('storage_directory')->nullable()->after('versiones');
        });
    }

    public function down(): void
    {
        Schema::table('esceanarios', function (Blueprint $table) {
            $table->dropColumn('storage_directory');
        });
    }
};
