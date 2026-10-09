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
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorcycle_id')->constrained()->restrictOnDelete();
            $table->date('performed_on');
            $table->unsignedInteger('odometer_km');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->decimal('cost_amount', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->index(['motorcycle_id', 'performed_on', 'id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
