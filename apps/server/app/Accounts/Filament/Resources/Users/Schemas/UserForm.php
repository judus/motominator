<?php

namespace App\Accounts\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('password')
                ->password()
                ->rule(Password::default())
                ->required(fn (string $operation): bool => $operation === 'create')
                ->formatStateUsing(fn (): string => '')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Leave blank when editing to keep the current password.'),
            ]);
    }
}
