<?php

namespace App\Activity\Filament\Resources\UserActivities;

use App\Activity\Enums\ActivityEvent;
use App\Activity\Filament\Resources\UserActivities\Pages\ManageUserActivities;
use App\Models\UserActivity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserActivityResource extends Resource
{
    protected static ?string $model = UserActivity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'User activity';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->dateTime()->sortable(),
            TextColumn::make('user.email')->label('Owner')->searchable(),
            TextColumn::make('actor.email')->label('Actor')->placeholder('System / deleted user'),
            TextColumn::make('event')->formatStateUsing(
                fn (ActivityEvent $state): string => $state->value
            )->searchable(),
            TextColumn::make('subject_type'),
            TextColumn::make('subject_id'),
            TextColumn::make('source')->badge(),
            TextColumn::make('changes')->state(
                fn (UserActivity $record): string => json_encode(
                    $record->changes,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                )
            )->wrap(),
            TextColumn::make('trace_id')->toggleable(isToggledHiddenByDefault: true)->copyable(),
        ])->filters([
            SelectFilter::make('event')->options(
                collect(ActivityEvent::cases())->mapWithKeys(
                    fn (ActivityEvent $event): array => [$event->value => $event->value]
                )->all()
            ),
            SelectFilter::make('source')->options(
                ['web' => 'Web', 'mobile' => 'Mobile', 'admin' => 'Admin', 'system' => 'System', 'ai' => 'AI']
            ),
            SelectFilter::make('user_id')->label('Owner')->relationship(
                'user',
                'email'
            )->searchable()->preload(),
        ])->recordActions([])->toolbarActions([])->recordUrl(null)->defaultSort(
            'id',
            'desc'
        );
    }

    /** @return Builder<UserActivity> */
    public static function getEloquentQuery(): Builder
    {
        $query = UserActivity::query()->with(['user', 'actor']);
        if (! auth()->user()?->is_admin) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function getPages(): array
    {
        return ['index' => ManageUserActivities::route('/')];
    }
}
