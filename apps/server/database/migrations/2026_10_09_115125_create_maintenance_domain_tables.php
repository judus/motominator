<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20);
            $table->string('disk', 50);
            $table->string('path');
            $table->string('filename');
            $table->string('mime', 64);
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });
        Schema::table('invoice_imports', function (Blueprint $table): void {
            $table->foreignId('evidence_document_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('maintenance_records', function (Blueprint $table): void {
            $table->string('origin', 20)->default('unknown');
            $table->string('performer', 20)->default('unknown');
            $table->string('performer_name')->nullable();
            $table->foreignId('workshop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('labor_minutes')->nullable();
        });
        Schema::create('maintenance_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('attached_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['maintenance_record_id', 'evidence_document_id'], 'maintenance_evidence_pair');
        });
        Schema::create('mileage_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('motorcycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('maintenance_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('evidence_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('observed_on');
            $table->decimal('odometer_value', 12, 3);
            $table->string('unit', 5)->default('km');
            $table->string('certainty', 20)->default('exact');
            $table->string('origin', 20)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['motorcycle_id', 'observed_on', 'id']);
        });
        Schema::create('maintenance_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_record_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('component', 100);
            $table->string('position', 100)->nullable();
            $table->text('notes')->nullable();
            $table->text('findings')->nullable();
            $table->timestamps();
        });
        Schema::create('maintenance_cost_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_invoice_item_id')->nullable()->unique(
                'cost_item_invoice_source'
            )->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('description', 1000)->nullable();
            $table->decimal('quantity', 12, 4)->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->decimal('tax_rate', 7, 4)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedInteger('labor_minutes')->nullable();
            $table->timestamps();
            $table->unique(['maintenance_record_id', 'position'], 'maintenance_cost_position');
        });
        Schema::create('maintenance_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('motorcycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('source', 20)->default('user');
            $table->text('source_reference')->nullable();
            $table->string('source_version')->nullable();
            $table->date('effective_on')->nullable();
            $table->timestamps();
        });
        Schema::create('maintenance_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('type', 20);
            $table->string('component', 100);
            $table->string('position', 100)->nullable();
            $table->string('schedule_kind', 20)->default('manual');
            $table->unsignedInteger('interval_km')->nullable();
            $table->unsignedSmallInteger('interval_months')->nullable();
            $table->date('target_on')->nullable();
            $table->unsignedInteger('target_odometer_km')->nullable();
            $table->date('baseline_on')->nullable();
            $table->unsignedInteger('baseline_odometer_km')->nullable();
            $table->string('source', 20)->nullable();
            $table->text('source_reference')->nullable();
            $table->string('source_version')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('maintenance_task_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_task_id')->constrained()->restrictOnDelete();
            $table->date('window_starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->unsignedInteger('due_odometer_km')->nullable();
            $table->string('status', 20)->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['id', 'maintenance_task_id'], 'occurrence_task_identity');
            $table->index(['maintenance_task_id', 'status']);
        });
        Schema::create('maintenance_fulfilments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('maintenance_task_occurrence_id');
            $table->foreignId('maintenance_task_id')->constrained()->restrictOnDelete();
            $table->foreignId('maintenance_action_id')->constrained()->restrictOnDelete();
            $table->foreignId('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('completed')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign(
                ['maintenance_task_occurrence_id', 'maintenance_task_id'],
                'fulfilment_occurrence_task'
            )->references(
                ['id', 'maintenance_task_id']
            )->on(
                'maintenance_task_occurrences'
            )->restrictOnDelete();
            $table->unique(['maintenance_task_id', 'maintenance_action_id'], 'action_fulfils_task_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_fulfilments');
        Schema::dropIfExists('maintenance_task_occurrences');
        Schema::dropIfExists('maintenance_tasks');
        Schema::dropIfExists('maintenance_plans');
        Schema::dropIfExists('maintenance_cost_items');
        Schema::dropIfExists('maintenance_actions');
        Schema::dropIfExists('mileage_readings');
        Schema::dropIfExists('maintenance_evidence');
        Schema::table('maintenance_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workshop_id');
            $table->dropConstrainedForeignId('recorded_by_id');
            $table->dropColumn(['origin', 'performer', 'performer_name', 'labor_minutes']);
        });
        Schema::table('invoice_imports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('evidence_document_id');
        });
        Schema::dropIfExists('evidence_documents');
    }
};
