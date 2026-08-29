<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Nama group awal untuk user yang sudah terdaftar. */
    private const DEFAULT_GROUP = 'Keluarga';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });

        // Backfill: seluruh user yang sudah ada dimasukkan ke satu group.
        // Hanya kolom group_id yang diisi; tidak ada data lain yang disentuh.
        $existing = DB::table('users')->orderBy('id')->pluck('id');

        if ($existing->isNotEmpty()) {
            $groupId = DB::table('groups')->insertGetId([
                'name' => self::DEFAULT_GROUP,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->whereIn('id', $existing)->update(['group_id' => $groupId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });
    }
};
