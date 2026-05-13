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
        Schema::create('layout_answers', function (Blueprint $table) {
            $table->foreignId('layout_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('contract_id')->constrained()->onDelete('cascade');
            $table->primary(['layout_field_id', 'contract_id']);
            $table->unique(['layout_field_id', 'contract_id']);
            $table->text('answer');
            $table->foreignId('answered_by')->nullable()->constrained('users');
            $table->timestamp('answered_at')->nullable();
            $table->enum('validation_status', ['valid', 'invalid', 'pending'])->default('pending');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('layout_answers');
    }
};
