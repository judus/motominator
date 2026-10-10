<?php

namespace App\Garage\Filament\Resources\Motorcycles\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MotorcyclesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns(
            [
                TextColumn::make('user.email')->label('Owner')->searchable(),
                TextColumn::make(
                    'make'
                )->searchable(),
                TextColumn::make(
                    'model'
                )->searchable(),
                TextColumn::make(
                    'year'
                )->sortable(),
                TextColumn::make(
                    'nickname'
                ),
                TextColumn::make(
                    'odometer_km'
                )->label(
                    'Mileage (km)'
                )->sortable(),
            ]
        )->recordActions(
            [EditAction::make()]
        )->defaultSort(
            'id',
            'desc'
        );
    }
}
