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
        Schema::create('layout_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layout_id')->constrained()->onDelete('cascade');
            $table->string('field_name');
            $table->unique(['field_name', 'layout_id']);
            $table->index('field_name');
            $table->softDeletes();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->text('default_value')->nullable();
            $table->text('validation_rules')->nullable();
            $table->json('options')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->enum('field_type', ['text', 'number', 'email', 'date', 'select', 'checkbox', 'textarea', 'file']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('layout_fields');
    }
};
