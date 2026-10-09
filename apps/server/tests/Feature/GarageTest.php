<?php

namespace Tests\Feature;

use App\Garage\Filament\Resources\MaintenanceRecords\Pages\CreateMaintenanceRecord;
use App\Garage\Filament\Resources\Motorcycles\Pages\CreateMotorcycle;
use App\Garage\Filament\Resources\Motorcycles\Pages\EditMotorcycle;
use App\Models\MaintenanceRecord;
use App\Models\Motorcycle;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GarageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, mixed> */
    private function bikeInput(): array
    {
        return ['make' => 'Honda', 'model' => 'CB500X', 'year' => 2022, 'odometer_km' => 12000];
    }

    /** @return array<string, mixed> */
    private function maintenanceInput(): array
    {
        return [
            'title' => 'Oil and filter change',
            'performed_on' => '2025-06-01',
            'odometer_km' => 15000,
            'notes' => 'Owner supplied record',
            'cost_amount' => '75.20',
            'currency' => 'CHF'
        ];
    }

    public function testGuestsCannotAccessTheGarage(): void
    {
        $this->getJson('/api/v1/motorcycles')->assertUnauthorized();
        $this->postJson('/api/v1/motorcycles', $this->bikeInput())->assertUnauthorized();
    }

    public function testMaintenanceDetailsRequireOwnershipAndTheCorrectMotorcycle(): void
    {
        $owner = User::factory()->create();
        $bike = Motorcycle::factory()->for($owner)->create();
        $otherBike = Motorcycle::factory()->for($owner)->create();
        $record = MaintenanceRecord::factory()->for($bike)->create(['cost_amount' => '75.20']);
        $path = "/api/v1/motorcycles/{$bike->id}/maintenance-records/{$record->id}";

        $this->getJson($path)->assertUnauthorized();
        Sanctum::actingAs($owner, ['garage:read']);
        $this->getJson($path)->assertOk()
            ->assertJsonPath('data.id', $record->id)->assertJsonPath('data.cost_amount', '75.20');
        $this->getJson(
            "/api/v1/motorcycles/{$otherBike->id}/maintenance-records/{$record->id}"
        )->assertNotFound();
        Sanctum::actingAs(User::factory()->create(), ['garage:read']);
        $this->getJson($path)->assertNotFound();
    }

    public function testMotorcyclesAreOwnedByTheActorAndOnlyTheirGarageIsListed(): void
    {
        $user = User::factory()->create();
        $foreign = Motorcycle::factory()->create();
        $response = $this->actingAs($user)->postJson(
            '/api/v1/motorcycles',
            $this->bikeInput() + ['user_id' => $foreign->user_id, 'id' => $foreign->id]
        )->assertCreated();
        $id = $this->integerValue($response->json('data.id'));
        $this->assertDatabaseHas('motorcycles', ['id' => $id, 'user_id' => $user->id, 'make' => 'Honda']);
        $this->getJson('/api/v1/motorcycles')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath(
            'data.0.id',
            $id
        )->assertJsonMissingPath(
            'data.0.user_id'
        );
        $this->getJson("/api/v1/motorcycles/{$id}")->assertOk();
        $this->putJson(
            "/api/v1/motorcycles/{$id}",
            array_replace($this->bikeInput(), ['nickname' => 'Daily rider', 'user_id' => $foreign->user_id])
        )->assertOk()->assertJsonPath(
            'data.nickname',
            'Daily rider'
        );
        $this->assertDatabaseHas('motorcycles', ['id' => $id, 'user_id' => $user->id]);
    }

    public function testForeignRecordsAndMismatchedParentBindingsReturnNotFound(): void
    {
        $user = User::factory()->create();
        $mine = Motorcycle::factory()->for($user)->create();
        $foreign = Motorcycle::factory()->create();
        $entry = MaintenanceRecord::factory()->for($foreign)->create();
        $this->actingAs($user);
        $this->getJson("/api/v1/motorcycles/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/motorcycles/{$foreign->id}", $this->bikeInput())->assertNotFound();
        $this->getJson("/api/v1/motorcycles/{$foreign->id}/maintenance-records")->assertNotFound();
        $this->postJson(
            "/api/v1/motorcycles/{$foreign->id}/maintenance-records",
            $this->maintenanceInput()
        )->assertNotFound();
        $this->putJson(
            "/api/v1/motorcycles/{$mine->id}/maintenance-records/{$entry->id}",
            $this->maintenanceInput()
        )->assertNotFound();
    }

    public function testUnverifiedUsersCanReadButCannotWrite(): void
    {
        $user = User::factory()->unverified()->create();
        $bike = Motorcycle::factory()->for($user)->create();
        $this->actingAs($user)->getJson('/api/v1/motorcycles')->assertOk();
        $this->postJson('/api/v1/motorcycles', $this->bikeInput())->assertForbidden();
        $this->postJson(
            "/api/v1/motorcycles/{$bike->id}/maintenance-records",
            $this->maintenanceInput()
        )->assertForbidden();
    }

    public function testNativePermissionsAreEnforcedForReadsAndWrites(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['account:read']);
        $this->getJson('/api/v1/motorcycles')->assertForbidden();
        $this->postJson('/api/v1/motorcycles', $this->bikeInput())->assertForbidden();
        Sanctum::actingAs($user, ['garage:read']);
        $this->getJson('/api/v1/motorcycles')->assertOk();
        $this->postJson('/api/v1/motorcycles', $this->bikeInput())->assertForbidden();
        Sanctum::actingAs($user, ['garage:read', 'garage:write']);
        $this->postJson('/api/v1/motorcycles', $this->bikeInput())->assertCreated();
    }

    public function testMaintenanceUpdatesMileageAtomicallyAndPreservesExactCosts(): void
    {
        $user = User::factory()->create();
        $bike = Motorcycle::factory()->for($user)->create();
        $path = "/api/v1/motorcycles/{$bike->id}/maintenance-records";
        $created = $this->actingAs($user)->postJson(
            $path,
            $this->maintenanceInput() + ['motorcycle_id' => 999]
        )->assertCreated()->assertJsonPath(
            'data.cost_amount',
            '75.20'
        )->assertJsonPath(
            'data.currency',
            'CHF'
        );
        $this->assertDatabaseHas('motorcycles', ['id' => $bike->id, 'odometer_km' => 15000]);
        $this->postJson(
            $path,
            array_replace(
                $this->maintenanceInput(),
                ['odometer_km' => 5000, 'performed_on' => '2024-06-01', 'cost_amount' => null, 'currency' => null]
            )
        )->assertCreated();
        $this->assertDatabaseHas('motorcycles', ['id' => $bike->id, 'odometer_km' => 15000]);
        $id = $this->integerValue($created->json('data.id'));
        $this->putJson(
            $path . "/{$id}",
            array_replace($this->maintenanceInput(), ['title' => 'Corrected entry', 'odometer_km' => 16000])
        )->assertOk()->assertJsonPath(
            'data.title',
            'Corrected entry'
        );
        $this->assertDatabaseHas('motorcycles', ['id' => $bike->id, 'odometer_km' => 16000]);
        $this->putJson(
            "/api/v1/motorcycles/{$bike->id}",
            $this->bikeInput()
        )->assertUnprocessable()->assertJsonValidationErrors(
            'odometer_km'
        );
        $this->assertDatabaseHas('motorcycles', ['id' => $bike->id, 'odometer_km' => 16000]);
    }

    #[TestWith(['cost_amount', '-1'])]
    #[TestWith(['cost_amount', '1.001'])]
    #[TestWith(['cost_amount', '1e2'])]
    #[TestWith(['cost_amount', '10000000000.00'])]
    #[TestWith(['currency', null])]
    #[TestWith(['currency', 'EURO'])]
    #[TestWith(['performed_on', '2025-02-30'])]
    #[TestWith(['performed_on', '2099-01-01'])]
    #[TestWith(['odometer_km', -1])]
    #[TestWith(['odometer_km', 1.5])]
    #[TestWith(['title', ''])]
    public function testInvalidMaintenanceDoesNotChangeAnyRecords(string $field, mixed $value): void
    {
        $user = User::factory()->create();
        $bike = Motorcycle::factory()->for($user)->create();
        $this->actingAs($user)->postJson(
            "/api/v1/motorcycles/{$bike->id}/maintenance-records",
            array_replace($this->maintenanceInput(), [$field => $value])
        )->assertUnprocessable()->assertJsonValidationErrors(
            $field
        );
        $this->assertDatabaseCount('maintenance_records', 0);
        $this->assertDatabaseHas('motorcycles', ['id' => $bike->id, 'odometer_km' => 10000]);
    }

    public function testRequiredMotorcycleFieldsAndInvalidValuesAreRejected(): void
    {
        $this->actingAs(User::factory()->create())->postJson(
            '/api/v1/motorcycles',
            []
        )->assertUnprocessable()->assertJsonValidationErrors(
            ['make', 'model', 'year', 'odometer_km']
        );
        $this->postJson(
            '/api/v1/motorcycles',
            array_replace($this->bikeInput(), ['year' => 1800, 'odometer_km' => -1])
        )->assertUnprocessable()->assertJsonValidationErrors(
            ['year', 'odometer_km']
        );
        $this->assertDatabaseCount('motorcycles', 0);
    }

    public function testHistoryHasStableDateOrderingAndPagination(): void
    {
        $user = User::factory()->create();
        $bike = Motorcycle::factory()->for($user)->create();
        $older = MaintenanceRecord::factory()->for($bike)->create(['performed_on' => '2024-01-01']);
        MaintenanceRecord::factory()->for($bike)->count(20)->create(['performed_on' => '2025-01-01']);
        $newest = MaintenanceRecord::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->getJson(
            "/api/v1/motorcycles/{$bike->id}/maintenance-records"
        )->assertOk()->assertJsonCount(
            20,
            'data'
        )->assertJsonPath(
            'data.0.id',
            $newest->id
        )->assertJsonPath(
            'meta.last_page',
            2
        );
        $this->getJson("/api/v1/motorcycles/{$bike->id}/maintenance-records?page=2")->assertJsonPath(
            'data.0.id',
            $older->id
        );
    }

    public function testPoliciesAllowOwnersAndAdminsAndDenyOtherUsersAndDeletion(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $bike = Motorcycle::factory()->for($owner)->create();
        $entry = MaintenanceRecord::factory()->for($bike)->create();
        foreach ([$bike, $entry] as $record) {
            foreach ([$owner, $admin] as $allowed) {
                $this->assertTrue(Gate::forUser($allowed)->allows('view', $record));
                $this->assertTrue(Gate::forUser($allowed)->allows('update', $record));
                $this->assertFalse(Gate::forUser($allowed)->allows('delete', $record));
            }
            $this->assertFalse(Gate::forUser($other)->allows('view', $record));
            $this->assertFalse(Gate::forUser($other)->allows('update', $record));
        }
        $this->actingAs($admin)->getJson("/api/v1/motorcycles/{$bike->id}")->assertNotFound();
    }

    public function testAdminFormsUseTheSameOperationsAndPreserveOwnership(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $this->actingAs($admin);
        Livewire::test(CreateMotorcycle::class)->fillForm($this->bikeInput() + ['user_id' => $owner->id])->call(
            'create'
        )->assertHasNoFormErrors();
        $bike = Motorcycle::query()->firstOrFail();
        $this->assertSame($owner->id, $bike->user_id);
        Livewire::test(CreateMaintenanceRecord::class)->fillForm(
            $this->maintenanceInput() + ['motorcycle_id' => $bike->id]
        )->call(
            'create'
        )->assertHasNoFormErrors();
        $this->assertDatabaseHas('motorcycles', ['id' => $bike->id, 'odometer_km' => 15000]);
        Livewire::test(EditMotorcycle::class, ['record' => $bike->id])->fillForm(['odometer_km' => 1])->call(
            'save'
        )->assertHasFormErrors(
            ['odometer_km']
        );
        $this->assertDatabaseHas('motorcycles', ['id' => $bike->id, 'odometer_km' => 15000]);
    }

    public function testRegularUsersCannotMountAdministrationForms(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        Livewire::test(CreateMotorcycle::class)->assertForbidden();
        Livewire::test(CreateMaintenanceRecord::class)->assertForbidden();
    }
}
