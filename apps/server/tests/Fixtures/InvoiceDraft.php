<?php

namespace Tests\Fixtures;

class InvoiceDraft
{
    /**
     * @return array{
     * invoice_number: string, performed_on: string, odometer_km: int, title: string, notes: null,
     * currency: string, subtotal_amount: string, tax_amount: string, total_amount: string, labor_minutes: int,
     * workshop: array{name: string, address: string, email: string, phone: null, tax_number: null},
     * items: list<array{description: string, quantity: string, unit_price: string, net_amount: string,
     * tax_rate: null, tax_amount: string, total_amount: string, labor_minutes: int}>
     * }
     */
    public static function data(): array
    {
        return [
            'invoice_number' => 'INV-2025-1',
            'performed_on' => '2025-06-01',
            'odometer_km' => 15000,
            'title' => 'Oil and filter service',
            'notes' => null,
            'currency' => 'CHF',
            'subtotal_amount' => '70.00',
            'tax_amount' => '5.20',
            'total_amount' => '75.20',
            'labor_minutes' => 30,
            'workshop' => [
                'name' => 'Local Workshop',
                'address' => 'Workshop Street 1',
                'email' => 'shop@example.com',
                'phone' => null,
                'tax_number' => null
            ],
            'items' => [
                [
                    'description' => 'Oil filter',
                    'quantity' => '1.0000',
                    'unit_price' => '70.0000',
                    'net_amount' => '70.00',
                    'tax_rate' => null,
                    'tax_amount' => '5.20',
                    'total_amount' => '75.20',
                    'labor_minutes' => 30
                ],
            ],
        ];
    }
}
