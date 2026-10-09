<?php

namespace App\Garage\Filament;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;

class GarageValidation
{
    public static function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /**
     * @param  Closure(): Model  $operation
     *
     * @throws ValidationException
     */
    public static function run(Closure $operation, ?string $statePath): Model
    {
        return Context::scope(fn (): Model => self::execute($operation, $statePath), ['activity_source' => 'admin']);
    }

    /** @param Closure(): Model $operation */
    private static function execute(Closure $operation, ?string $statePath): Model
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            $messages = [];
            foreach ($exception->errors() as $field => $errors) {
                $messages[filled($statePath) ? $statePath . '.' . $field : $field] = $errors;
            }
            throw ValidationException::withMessages($messages);
        }
    }
}
