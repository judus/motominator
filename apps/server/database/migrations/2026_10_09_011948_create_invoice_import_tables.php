<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('identity', 64);
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'identity']);
        });
        Schema::create('invoice_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('motorcycle_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('filename');
            $table->string('mime', 64);
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->string('status', 20)->default('uploaded');
            $table->unsignedInteger('version')->default(0);
            $table->uuid('attempt_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->text('draft')->nullable();
            $table->string('error')->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamps();
            $table->index(['motorcycle_id', 'id']);
        });
        Schema::create('maintenance_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_import_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_record_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('workshop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 100)->nullable();
            $table->decimal('subtotal_amount', 12, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedInteger('labor_minutes')->nullable();
            $table->timestamps();
        });
        Schema::create('maintenance_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('description', 1000)->nullable();
            $table->decimal('quantity', 12, 4)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->decimal('tax_rate', 7, 4)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->unsignedInteger('labor_minutes')->nullable();
            $table->unique(['maintenance_invoice_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_invoice_items');
        Schema::dropIfExists('maintenance_invoices');
        Schema::dropIfExists('invoice_imports');
        Schema::dropIfExists('workshops');
    }
};
