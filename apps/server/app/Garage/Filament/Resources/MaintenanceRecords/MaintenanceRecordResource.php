<?php

namespace App\Garage\Filament\Resources\MaintenanceRecords;

use App\Garage\Filament\Resources\MaintenanceRecords\Pages\CreateMaintenanceRecord;
use App\Garage\Filament\Resources\MaintenanceRecords\Pages\EditMaintenanceRecord;
use App\Garage\Filament\Resources\MaintenanceRecords\Pages\ListMaintenanceRecords;
use App\Garage\Filament\Resources\MaintenanceRecords\Schemas\MaintenanceRecordForm;
use App\Garage\Filament\Resources\MaintenanceRecords\Tables\MaintenanceRecordsTable;
use App\Models\MaintenanceRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceRecordResource extends Resource
{
    protected static ?string $model = MaintenanceRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_admin === true;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->is_admin === true;
    }

    /** @return Builder<MaintenanceRecord> */
    public static function getEloquentQuery(): Builder
    {
        $query = MaintenanceRecord::query();
        if (! auth()->user()?->is_admin) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return MaintenanceRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceRecordsTable::configure($table);
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
            'index' => ListMaintenanceRecords::route('/'),
            'create' => CreateMaintenanceRecord::route('/create'),
            'edit' => EditMaintenanceRecord::route('/{record}/edit'),
        ];
    }
}
