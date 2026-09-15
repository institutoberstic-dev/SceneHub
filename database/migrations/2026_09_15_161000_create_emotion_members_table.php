<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emotion_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_meeting');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('access_level', 30)->default('viewer');
            $table->timestamps();
            $table->unique(['id_meeting', 'user_id']);
            $table->index(['id_meeting', 'access_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emotion_members');
    }
};
