<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat approval / workflow (polymorphic) untuk transaksi apa pun.
     * Mencatat siapa melakukan aksi apa, kapan, dengan catatan apa.
     */
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->enum('action', [
                'submitted',
                'verified',
                'approved',
                'rejected',
                'posted',
                'reversed',
            ]);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id', 'action'], 'approvals_approvable_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
