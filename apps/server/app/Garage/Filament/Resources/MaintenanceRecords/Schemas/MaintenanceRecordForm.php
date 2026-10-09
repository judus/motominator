<?php

namespace App\Garage\Filament\Resources\MaintenanceRecords\Schemas;

use App\Models\Motorcycle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('motorcycle_id')->label('Motorcycle')->relationship(
                'motorcycle',
                'make',
                modifyQueryUsing: fn (Builder $query) => $query->with('user')
            )->getOptionLabelFromRecordUsing(
                fn (Motorcycle $record): string => "{$record->make} {$record->model} ({$record->user->email})"
            )->searchable()->required()->disabledOn(
                'edit'
            ),
            DatePicker::make('performed_on')->required()->maxDate(today()),
            TextInput::make('odometer_km')->label(
                'Mileage (km)'
            )->numeric()->required()->minValue(
                0
            ),
            TextInput::make('title')->label('Work performed')->required()->maxLength(255),
            Textarea::make('notes')->maxLength(10000),
            TextInput::make('cost_amount')->label(
                'Cost (up to 2 decimals)'
            )->numeric()->minValue(
                0
            ),
            TextInput::make('currency')->label('Currency code, e.g. CHF')->length(3),
        ]);
    }
}
