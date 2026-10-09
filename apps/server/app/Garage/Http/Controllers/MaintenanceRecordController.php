<?php

namespace App\Garage\Http\Controllers;

use App\Garage\Actions\SaveMaintenanceRecord;
use App\Garage\Http\Resources\MaintenanceRecordResource;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use App\Models\Motorcycle;
use App\Support\Input;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaintenanceRecordController extends Controller
{
    public function index(Request $request, Motorcycle $motorcycle): AnonymousResourceCollection
    {
        abort_unless($motorcycle->user_id === $this->authenticatedUser($request)->id, 404);

        return MaintenanceRecordResource::collection(
            $motorcycle->maintenanceRecords()->orderByDesc('performed_on')->orderByDesc('id')->paginate(20)
        );
    }

    public function store(
        Request $request,
        Motorcycle $motorcycle,
        SaveMaintenanceRecord $save
    ): MaintenanceRecordResource {
        abort_unless($motorcycle->user_id === $this->authenticatedUser($request)->id, 404);

        return new MaintenanceRecordResource(
            $save($this->authenticatedUser($request), $motorcycle, Input::object($request->all()))
        );
    }

    public function show(
        Request $request,
        Motorcycle $motorcycle,
        MaintenanceRecord $maintenanceRecord,
    ): MaintenanceRecordResource {
        abort_unless($motorcycle->user_id === $this->authenticatedUser($request)->id, 404);

        return new MaintenanceRecordResource($maintenanceRecord);
    }

    public function update(
        Request $request,
        Motorcycle $motorcycle,
        MaintenanceRecord $maintenanceRecord,
        SaveMaintenanceRecord $save
    ): MaintenanceRecordResource {
        abort_unless($motorcycle->user_id === $this->authenticatedUser($request)->id, 404);

        return new MaintenanceRecordResource(
            $save($this->authenticatedUser($request), $motorcycle, Input::object(
                $request->all()
            ), $maintenanceRecord)
        );
    }
}
