<?php

namespace App\Ai\Tools;

use App\Garage\Actions\ReadGarageContext;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListMyMotorcycles implements Tool
{
    public function __construct(private readonly User $actor, private readonly ReadGarageContext $garage)
    {
    }

    public function description(): string
    {
        return 'List the signed-in rider\'s motorcycles. Follow pagination when more motorcycles are available.';
    }

    public function handle(Request $request): string
    {
        $page = $request['page'];

        return json_encode($this->garage->motorcycles($this->actor, is_int($page) ? $page : 1), JSON_THROW_ON_ERROR);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['page' => $schema->integer()->min(1)->max(10000)->required()];
    }
}
