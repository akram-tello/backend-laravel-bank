<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('amount', 10, 2);
            $table->enum('type', ['DEPOSIT', 'TRANSFER']);
            $table->string('description')->nullable();
            $table->string('recipient_ref')->nullable();
            $table->foreignUuid('from_account_id')->constrained('accounts')->onDelete('cascade');
            $table->foreignUuid('to_account_id')->constrained('accounts')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
