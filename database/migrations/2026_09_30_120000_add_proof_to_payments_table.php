<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payer_name', 100)->nullable()->after('gateway_ref');   // nama pengirim / pemilik rekening
            $table->string('proof')->nullable()->after('payer_name');              // path bukti transfer (disk "local", privat)
            $table->dateTime('proof_uploaded_at')->nullable()->after('proof');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payer_name', 'proof', 'proof_uploaded_at']);
        });
    }
};
