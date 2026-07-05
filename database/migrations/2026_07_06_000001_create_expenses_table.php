<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('treasury_id')
                ->constrained('treasury_accounts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // wage -> Employee, refund -> Collection, general -> null
            $table->enum('expense_type', ['wage', 'refund', 'general'])->index();

            // Polymorphic target (employee for wages, collection for refunds).
            // Nullable because 'general' expenses have no related model.
            $table->nullableMorphs('expensable');

            $table->decimal('amount', 12, 2);
            $table->date('expense_date')->index();
            $table->text('description')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
