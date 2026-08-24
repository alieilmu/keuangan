<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 10); // income | expense
            $table->string('color', 9)->default('#64748b');
            $table->string('icon', 40)->nullable();
            $table->timestamps();

            // Nama kategori boleh sama antara income & expense, tapi unik per tipe.
            $table->unique(['user_id', 'type', 'name']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
