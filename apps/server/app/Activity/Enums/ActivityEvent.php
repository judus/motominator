<?php

namespace App\Activity\Enums;

enum ActivityEvent: string
{
    case MotorcycleCreated = 'motorcycle.created';
    case MotorcycleUpdated = 'motorcycle.updated';
    case MaintenanceCreated = 'maintenance.created';
    case MaintenanceUpdated = 'maintenance.updated';
    case AiSettingsSaved = 'ai.settings_saved';
    case AiSettingsRemoved = 'ai.settings_removed';
    case InvoiceUploaded = 'invoice.uploaded';
    case InvoiceExtractionRequested = 'invoice.extraction_requested';
    case InvoiceDraftUpdated = 'invoice.draft_updated';
    case InvoiceDraftReady = 'invoice.draft_ready';
    case InvoiceConfirmed = 'invoice.confirmed';
    case WorkshopSaved = 'workshop.saved';
    case EvidenceAttached = 'maintenance.evidence_attached';
    case MaintenanceTaskFulfilled = 'maintenance.task_fulfilled';

    public function subjectType(): string
    {
        return match ($this) {
            self::InvoiceUploaded,
                self::InvoiceExtractionRequested,
                self::InvoiceDraftReady,
                self::InvoiceDraftUpdated,
                self::InvoiceConfirmed => 'invoice_import',

            self::WorkshopSaved => 'workshop',
            self::EvidenceAttached => 'maintenance_record',
            self::MaintenanceTaskFulfilled => 'maintenance_fulfilment',
            self::MotorcycleCreated, self::MotorcycleUpdated => 'motorcycle',
            self::MaintenanceCreated, self::MaintenanceUpdated => 'maintenance_record',
            self::AiSettingsSaved, self::AiSettingsRemoved => 'ai_settings',
        };
    }

    /** @return list<string> */
    public function fields(): array
    {
        return match ($this) {
            self::InvoiceUploaded,
                self::InvoiceExtractionRequested,
                self::InvoiceDraftReady,
                self::InvoiceDraftUpdated,
                self::InvoiceConfirmed,
                self::WorkshopSaved,
                self::EvidenceAttached,
                self::MaintenanceTaskFulfilled => [],

            self::MotorcycleCreated, self::MotorcycleUpdated => ['make', 'model', 'year', 'nickname', 'odometer_km'],
            self::MaintenanceCreated,
                self::MaintenanceUpdated => [
                    'motorcycle_id',
                    'performed_on',
                    'odometer_km',
                    'title',
                    'notes',
                    'cost_amount',
                    'currency',
                ],

            self::AiSettingsSaved, self::AiSettingsRemoved => ['provider', 'model'],
        };
    }
}
