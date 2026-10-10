<?php

namespace App\Ai\Tools;

use App\Garage\Actions\ReadGarageContext;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetMotorcycleHistory implements Tool
{
    public function __construct(private readonly User $actor, private readonly ReadGarageContext $garage)
    {
    }

    public function description(): string
    {
        return 'Read one of this rider\'s motorcycles and its latest 30 maintenance records and mileage readings. '
            . 'Counts reveal omitted older history; notes may be truncated. '
            . 'Missing records do not prove no service occurred.';
    }

    public function handle(Request $request): string
    {
        $id = $request['motorcycle_id'];
        if (! is_int($id)) {
            return 'Provide the integer motorcycle_id returned by ListMyMotorcycles.';
        }
        try {
            return json_encode($this->garage->motorcycle($this->actor, $id), JSON_THROW_ON_ERROR);
        } catch (ModelNotFoundException | AuthorizationException) {
            return 'That motorcycle is not available. Use ListMyMotorcycles to select an accessible motorcycle.';
        }
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['motorcycle_id' => $schema->integer()->min(1)->required()];
    }
}
