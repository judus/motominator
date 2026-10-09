<?php

namespace App\Garage\Actions\Invoices;

use App\Support\Input;
use Illuminate\Support\Facades\Validator;

class ValidateInvoiceDraft
{
    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function __invoke(array $input, bool $confirm = false): array
    {
        $money = ['nullable', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'];
        $lineMoney = ['nullable', 'string', 'regex:/^-?\d{1,10}(\.\d{1,2})?$/'];
        $required = $confirm ? 'required' : 'nullable';

        return Input::object(Validator::make($input, [
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'performed_on' => [$required, 'date_format:Y-m-d', 'before_or_equal:today'],
            'odometer_km' => [$required, 'integer', 'min:0', 'max:4294967295'],
            'title' => [$required, 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'currency' => [
                'nullable',
                $confirm ? 'required_with:total_amount,subtotal_amount,tax_amount,items.*.total_amount,items'
                . '.*.net_amount,items.*.unit_price,items.*.tax_amount' : 'sometimes',
                'regex:/^[A-Z]{3}$/',
            ],
            'subtotal_amount' => $money,
            'tax_amount' => $money,
            'total_amount' => $money,
            'labor_minutes' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'workshop' => ['required', 'array:name,address,email,phone,tax_number'],
            'workshop.name' => [
                'nullable',
                $confirm
                ? 'required_with:workshop.address,workshop.email,workshop.phone,workshop.tax_number'
                : 'sometimes',
                'string',
                'max:255',
            ],
            'workshop.address' => ['nullable', 'string', 'max:2000'],
            'workshop.email' => ['nullable', 'email', 'max:255'],
            'workshop.phone' => ['nullable', 'string', 'max:100'],
            'workshop.tax_number' => ['nullable', 'string', 'max:100'],
            'items' => ['present', 'array', 'list', 'max:100'],
            'items.*' => ['array:description,quantity,unit_price,net_amount,tax_rate,tax_amount,total_amoun'
                . 't,labor_minutes'
            ],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['nullable', 'string', 'regex:/^\d{1,8}(\.\d{1,4})?$/'],
            'items.*.unit_price' => ['nullable', 'string', 'regex:/^-?\d{1,10}(\.\d{1,4})?$/'],
            'items.*.net_amount' => $lineMoney,
            'items.*.tax_rate' => ['nullable', 'string', 'regex:/^\d{1,3}(\.\d{1,4})?$/', 'numeric', 'max:100'],
            'items.*.tax_amount' => $lineMoney,
            'items.*.total_amount' => $lineMoney,
            'items.*.labor_minutes' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ])->validate());
    }
}
