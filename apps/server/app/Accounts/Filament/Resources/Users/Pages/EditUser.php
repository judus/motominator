<?php

namespace App\Accounts\Filament\Resources\Users\Pages;

use App\Accounts\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->getAttribute('email') !== $data['email']) {
                $locked->setAttribute('email_verified_at', null);
            }
            $locked->update($data);

            return $locked;
        });
    }
}
