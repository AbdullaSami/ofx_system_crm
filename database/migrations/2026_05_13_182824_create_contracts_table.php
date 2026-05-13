<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('restrict');
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('set null');
            $table->string('contract_number')->unique();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->decimal('discount', 5, 2)->default(0);
            $table->enum('status', ['draft', 'active', 'expired', 'terminated', 'renewed'])->default('draft');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
            $table->index(['client_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->foreignId('signed_by')->nullable()->constrained('users');
            $table->string('document_path')->nullable();
            $table->string('billing_cycle')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->date('renewal_date')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
