<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classifications', function (Blueprint $table) {
            // Snapshot of the text that was classified. Nullable only so existing
            // local rows can migrate; new writes always set a value.
            $table->text('message')->nullable()->after('customer_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('classifications', function (Blueprint $table) {
            $table->dropColumn('message');
        });
    }
};
