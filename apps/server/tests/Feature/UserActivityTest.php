<?php

namespace Tests\Feature;

use App\Activity\Actions\GetUserActivity;
use App\Activity\Enums\ActivityEvent;
use App\Activity\Filament\Resources\UserActivities\Pages\ManageUserActivities;
use App\Garage\Actions\SaveMotorcycle;
use App\Models\Motorcycle;
use App\Models\User;
use App\Models\UserActivity;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class UserActivityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function testActivityAttributesSuccessfulHttpChangesAndIgnoresNoopAndInvalidUpdates(): void
    {
        $user = User::factory()->create();
        $input = ['make' => 'Honda', 'model' => 'CB500X', 'year' => 2022, 'odometer_km' => 12000];

        $response = $this->actingAs($user)->postJson('/api/v1/motorcycles', $input)->assertCreated();
        $activity = UserActivity::query()->sole();
        $this->assertSame($user->id, $activity->user_id);
        $this->assertSame($user->id, $activity->actor_id);
        $this->assertSame('web', $activity->source);
        $this->assertSame($response->headers->get('X-Request-ID'), $activity->trace_id);
        $this->assertSame(ActivityEvent::MotorcycleCreated, $activity->event);
        $this->assertSame(null, $activity->changes['make']['before']);
        $this->assertSame('Honda', $activity->changes['make']['after']);
        $id = $this->integerValue($response->json('data.id'));
        $this->putJson("/api/v1/motorcycles/{$id}", $input)->assertOk();
        $this->putJson("/api/v1/motorcycles/{$id}", ['year' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('user_activities', 1);
    }

    public function testMaintenanceActivityOmitsFreeTextAndPreservesExactMoneyAndMileageChanges(): void
    {
        $user = User::factory()->create();
        $bike = Motorcycle::factory()->for($user)->create();

        $this->actingAs($user)->postJson("/api/v1/motorcycles/{$bike->id}/maintenance-records", [
            'title' => 'Private title',
            'notes' => 'Private invoice text',
            'performed_on' => '2025-06-01',
            'odometer_km' => 15000,
            'cost_amount' => '75.20',
            'currency' => 'CHF',
        ])->assertCreated();

        $activity = UserActivity::query()->where('event', 'maintenance.created')->sole();
        $this->assertSame(null, $activity->changes['cost_amount']['before']);
        $this->assertSame('75.20', $activity->changes['cost_amount']['after']);
        $this->assertSame(['changed' => true], $activity->changes['notes']);
        $this->assertStringNotContainsString('Private', $activity->toJson());
        $this->assertDatabaseHas('user_activities', ['event' => 'motorcycle.updated', 'subject_id' => $bike->id]);
    }

    public function testDeviceTokensAreAttributedAsMobileAndForeignWritesCreateNoActivity(): void
    {
        $user = User::factory()->create();
        $foreign = Motorcycle::factory()->create();
        $token = $user->createToken('test-device', ['garage:read', 'garage:write'])->plainTextToken;
        $input = ['make' => 'Honda', 'model' => 'CB500X', 'year' => 2022, 'odometer_km' => 12000];

        $this->withToken($token)->postJson('/api/v1/motorcycles', $input)->assertCreated();
        $this->putJson("/api/v1/motorcycles/{$foreign->id}", $input)->assertNotFound();

        $this->assertDatabaseCount('user_activities', 1);
        $this->assertDatabaseHas('user_activities', ['user_id' => $user->id, 'source' => 'mobile']);
    }

    public function testFreshlyLockedAiOwnersKeepNativeActivityAttribution(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device', ['ai:write'])->plainTextToken;

        $this->withToken($token)->putJson('/api/v1/ai/settings', [
            'provider' => 'openai', 'model' => 'test-model', 'api_key' => 'native-test-key-1234',
        ])->assertOk();

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'event' => 'ai.settings_saved',
            'source' => 'mobile',
        ]);
    }

    public function testActivityRollsBackWithItsDomainChangeAndKeepsAdminActorSeparateFromOwner(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $input = ['make' => 'Honda', 'model' => 'CB500X', 'year' => 2022, 'odometer_km' => 0];
        $connection = DB::connection();
        $connection->beginTransaction();
        try {
            Context::scope(
                fn () => app(SaveMotorcycle::class)($admin, $input, owner: $owner),
                ['activity_source' => 'admin']
            );
            $activity = UserActivity::query()->sole();
            $this->assertSame($admin->id, $activity->actor_id);
            $this->assertSame($owner->id, $activity->user_id);
            $this->assertSame('admin', $activity->source);
        } finally {
            $connection->rollBack();
        }

        $this->assertDatabaseCount('motorcycles', 0);
        $this->assertDatabaseCount('user_activities', 0);
    }

    public function testAiActivityNeverCopiesKeyOrHintAndRepeatedRemovalIsANoop(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->putJson(
            '/api/v1/ai/settings',
            ['provider' => 'openai', 'model' => 'test-model', 'api_key' => 'sk-test-private-123456789']
        )->assertOk();
        $activity = UserActivity::query()->sole();
        $keys = array_keys($activity->changes);
        sort($keys);
        $this->assertSame(['model', 'provider'], $keys);
        $this->assertStringNotContainsString('123456789', $activity->toJson());
        $this->deleteJson('/api/v1/ai/settings')->assertOk();
        $this->deleteJson('/api/v1/ai/settings')->assertOk();

        $this->assertDatabaseCount('user_activities', 2);
        $this->assertDatabaseHas('user_activities', ['event' => 'ai.settings_removed', 'user_id' => $user->id]);
    }

    public function testActivityFeedIsOwnerScopedBoundedAndNewestFirst(): void
    {
        $owner = User::factory()->create();
        $old = UserActivity::factory()->for($owner, 'user')->create();
        $new = UserActivity::factory()->for($owner, 'user')->create();
        UserActivity::factory()->create();

        $feed = app(GetUserActivity::class)($owner, 1);

        $this->assertSame([$new->id], $feed->modelKeys());
        $this->assertSame([$new->id, $old->id], app(GetUserActivity::class)($owner)->modelKeys());
    }

    public function testActivityAdministrationIsReadOnlyAndRegularUsersCannotMountIt(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['is_admin' => true]);
        $regular = User::factory()->create();
        $activity = UserActivity::factory()->create();
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', UserActivity::class));
        foreach (['create', 'deleteAny'] as $ability) {
            $this->assertFalse(Gate::forUser($admin)->allows($ability, UserActivity::class));
        }
        foreach (['update', 'delete'] as $ability) {
            $this->assertFalse(Gate::forUser($admin)->allows($ability, $activity));
        }
        $this->actingAs($admin);
        Livewire::test(ManageUserActivities::class)->assertCanSeeTableRecords([$activity]);
        $this->actingAs($regular);
        Livewire::test(ManageUserActivities::class)->assertForbidden();
    }

    public function testRetentionPrunesExpiredActivityOnly(): void
    {
        $this->freezeTime();
        config(['logging.activity_retention_days' => 30]);
        $old = UserActivity::factory()->create(['created_at' => now()->subDays(31)]);
        $recent = UserActivity::factory()->create(['created_at' => now()->subDays(29)]);

        $this->pendingCommand('model:prune', ['--model' => [UserActivity::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }
}
