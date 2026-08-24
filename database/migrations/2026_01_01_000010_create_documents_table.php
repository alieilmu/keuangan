<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Pemilik dokumen: Bill (berkas tagihan) atau Transaction (nota pembayaran).
            $table->morphs('documentable');
            $table->string('kind', 12); // invoice | receipt
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size'); // byte
            $table->timestamps();

            $table->index(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
