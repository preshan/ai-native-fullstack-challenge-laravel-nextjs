<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_request_id')->nullable()->constrained('customer_requests')->nullOnDelete();
            $table->string('category', 32);
            $table->double('confidence');
            $table->string('source', 32)->default('keyword');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classifications');
    }
};
