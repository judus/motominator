<?php

namespace App\Garage\Filament\Resources\Motorcycles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MotorcycleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Owner')->relationship(
                'user',
                'email'
            )->searchable()->required()->disabledOn(
                'edit'
            ),
            TextInput::make('make')->required()->maxLength(100),
            TextInput::make('model')->required()->maxLength(100),
            TextInput::make('year')->numeric()->required()->minValue(1885)->maxValue(
                now()->year + 1
            ),
            TextInput::make('nickname')->maxLength(100),
            TextInput::make('odometer_km')->label(
                'Mileage (km)'
            )->numeric()->required()->minValue(
                0
            )->default(
                0
            ),
        ]);
    }
}
