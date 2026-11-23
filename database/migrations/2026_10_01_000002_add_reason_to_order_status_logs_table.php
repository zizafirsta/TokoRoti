<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alasan perubahan status (wajib untuk pembatalan) - RQ-023, RQ-024
        Schema::table('order_status_logs', function (Blueprint $table) {
            $table->string('reason', 255)->nullable()->after('changed_by');
        });
    }

    public function down(): void
    {
        Schema::table('order_status_logs', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }
};
