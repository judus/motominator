<?php

namespace App\Garage\Http\Controllers;

use App\Garage\Actions\SaveMotorcycle;
use App\Garage\Http\Resources\MotorcycleResource;
use App\Http\Controllers\Controller;
use App\Models\Motorcycle;
use App\Support\Input;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MotorcycleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return MotorcycleResource::collection(
            $this->authenticatedUser($request)->motorcycles()->orderByDesc('id')->paginate(20)
        );
    }

    public function store(Request $request, SaveMotorcycle $save): MotorcycleResource
    {
        return new MotorcycleResource(
            $save($this->authenticatedUser($request), Input::object($request->all()))
        );
    }

    public function show(Request $request, Motorcycle $motorcycle): MotorcycleResource
    {
        abort_unless($motorcycle->user_id === $this->authenticatedUser($request)->id, 404);

        return new MotorcycleResource($motorcycle);
    }

    public function update(Request $request, Motorcycle $motorcycle, SaveMotorcycle $save): MotorcycleResource
    {
        abort_unless($motorcycle->user_id === $this->authenticatedUser($request)->id, 404);

        return new MotorcycleResource(
            $save($this->authenticatedUser($request), Input::object($request->all()), $motorcycle)
        );
    }
}
