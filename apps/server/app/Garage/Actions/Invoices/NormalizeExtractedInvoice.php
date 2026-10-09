<?php

namespace App\Garage\Actions\Invoices;

class NormalizeExtractedInvoice
{
    /**
     * Normalize an explicit percentage's notation, without inferring its value.
     * Manual review submissions still pass through authoritative validation.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function __invoke(array $input): array
    {
        $items = $input['items'] ?? null;
        if (! is_array($items)) {
            return $input;
        }
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $rate = $item['tax_rate'] ?? null;
            if (is_string($rate) && preg_match('/^\s*(\d{1,3}(?:[.,]\d{1,4})?)\s*%?\s*$/D', $rate, $matches)) {
                $item['tax_rate'] = str_replace(',', '.', $matches[1]);
                $items[$index] = $item;
            }
        }

        $input['items'] = $items;

        return $input;
    }
}
