<?php

namespace App\Garage\Filament\Resources\MaintenanceRecords\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaintenanceRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns(
            [
                TextColumn::make('motorcycle.make')->label('Make'),
                TextColumn::make('motorcycle.model')->label(
                    'Model'
                ),
                TextColumn::make(
                    'motorcycle.user.email'
                )->label(
                    'Owner'
                ),
                TextColumn::make(
                    'performed_on'
                )->date()->sortable(),
                TextColumn::make(
                    'title'
                )->label(
                    'Work performed'
                )->searchable(),
                TextColumn::make(
                    'odometer_km'
                )->label(
                    'Mileage (km)'
                ),
                TextColumn::make(
                    'cost_amount'
                ),
                TextColumn::make(
                    'currency'
                ),
            ]
        )->recordActions(
            [EditAction::make()]
        )->defaultSort(
            'performed_on',
            'desc'
        );
    }
}
