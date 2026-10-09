<?php

namespace App\Garage\Filament\Resources\Motorcycles;

use App\Garage\Filament\Resources\Motorcycles\Pages\CreateMotorcycle;
use App\Garage\Filament\Resources\Motorcycles\Pages\EditMotorcycle;
use App\Garage\Filament\Resources\Motorcycles\Pages\ListMotorcycles;
use App\Garage\Filament\Resources\Motorcycles\Schemas\MotorcycleForm;
use App\Garage\Filament\Resources\Motorcycles\Tables\MotorcyclesTable;
use App\Models\Motorcycle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MotorcycleResource extends Resource
{
    protected static ?string $model = Motorcycle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nickname';

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_admin === true;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->is_admin === true;
    }

    /** @return Builder<Motorcycle> */
    public static function getEloquentQuery(): Builder
    {
        $query = Motorcycle::query();
        if (! auth()->user()?->is_admin) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return MotorcycleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MotorcyclesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMotorcycles::route('/'),
            'create' => CreateMotorcycle::route('/create'),
            'edit' => EditMotorcycle::route('/{record}/edit'),
        ];
    }
}
