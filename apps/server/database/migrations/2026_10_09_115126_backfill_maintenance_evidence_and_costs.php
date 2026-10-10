<?php

use App\Support\Input;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('invoice_imports')->whereNull('evidence_document_id')->orderBy(
                'id'
            )->chunkById(
                100,
                function ($imports): void {
                    foreach ($imports as $import) {
                        $documentId = DB::table('evidence_documents')->insertGetId([
                            'user_id' => $import->user_id,
                            'kind' => 'invoice',
                            'disk' => 'invoices',
                            'path' => $import->path,
                            'filename' => $import->filename,
                            'mime' => $import->mime,
                            'size' => $import->size,
                            'sha256' => $import->sha256,
                            'created_at' => $import->created_at,
                            'updated_at' => $import->updated_at,
                        ]);
                        DB::table('invoice_imports')->where('id', $import->id)->update(
                            ['evidence_document_id' => $documentId]
                        );
                    }
                }
            );
            DB::table('maintenance_invoices')->orderBy('id')->chunkById(100, function ($invoices): void {
                foreach ($invoices as $invoice) {
                    $documentId = DB::table('invoice_imports')->where('id', $invoice->invoice_import_id)->value(
                        'evidence_document_id'
                    );
                    DB::table('maintenance_evidence')->updateOrInsert([
                        'maintenance_record_id' => $invoice->maintenance_record_id,
                        'evidence_document_id' => $documentId,
                    ], ['created_at' => $invoice->created_at, 'updated_at' => $invoice->updated_at]);
                    DB::table('maintenance_records')->where('id', $invoice->maintenance_record_id)->update([
                        'origin' => 'invoice',
                        'performer' => $invoice->workshop_id ? 'workshop' : 'unknown',
                        'workshop_id' => $invoice->workshop_id,
                        'labor_minutes' => $invoice->labor_minutes,
                    ]);
                    foreach (
                        DB::table('maintenance_invoice_items')->where(
                            'maintenance_invoice_id',
                            $invoice->id
                        )->get() as $item
                    ) {
                        $data = Input::object(get_object_vars($item));
                        unset($data['id'], $data['maintenance_invoice_id']);
                        DB::table(
                            'maintenance_cost_items'
                        )->updateOrInsert(
                            ['maintenance_invoice_item_id' => $item->id],
                            $data + [
                                'maintenance_record_id' => $invoice->maintenance_record_id,
                                'currency' => $invoice->currency,
                                'created_at' => $invoice->created_at,
                                'updated_at' => $invoice->updated_at,
                            ]
                        );
                    }
                }
            });
        });
    }

    public function down(): void
    {
        // The preceding schema migration removes the additive projections. Original invoice data is retained.
    }
};
