<?php

namespace Database\Factories;

use App\Garage\Enums\EvidenceKind;
use App\Models\EvidenceDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvidenceDocument> */
class EvidenceDocumentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => EvidenceKind::Receipt,
            'disk' => 'invoices',
            'path' => 'fixture-receipt.jpg',
            'filename' => 'receipt.jpg',
            'mime' => 'image/jpeg',
            'size' => 100,
            'sha256' => hash('sha256', 'fixture'),
        ];
    }
}
