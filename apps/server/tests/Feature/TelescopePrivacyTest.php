<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Motorcycle;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Telescope;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TelescopePrivacyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function createApplication(): Application
    {
        $environment = $_ENV['APP_ENV'] ?? null;
        $serverEnvironment = $_SERVER['APP_ENV'] ?? null;
        $enabled = $_ENV['TELESCOPE_ENABLED'] ?? null;
        $serverEnabled = $_SERVER['TELESCOPE_ENABLED'] ?? null;
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'local';
        $_ENV['TELESCOPE_ENABLED'] = $_SERVER['TELESCOPE_ENABLED'] = 'true';

        try {
            return parent::createApplication();
        } finally {
            $_ENV['APP_ENV'] = $environment;
            $_SERVER['APP_ENV'] = $serverEnvironment;
            $_ENV['TELESCOPE_ENABLED'] = $enabled;
            $_SERVER['TELESCOPE_ENABLED'] = $serverEnabled;
        }
    }

    protected function tearDown(): void
    {
        Telescope::stopRecording();
        Telescope::flushEntries();
        Telescope::$filterUsing = [];
        parent::tearDown();
    }

    public function testLocalBootstrapLoadsMaskingAndKeepsOrdinaryRequestDiagnostics(): void
    {
        $this->assertInstanceOf(
            TelescopeServiceProvider::class,
            $this->app->getProvider(TelescopeServiceProvider::class)
        );
        Telescope::flushEntries();
        Telescope::startRecording(false);

        $this->withHeader('Cookie', 'session=private-cookie-sentinel')->getJson('/api/v1/status')->assertOk();

        $entry = DB::table('telescope_entries')->where('type', 'request')->sole();
        $content = $this->stringValue($entry->content);
        $this->assertSame('/api/v1/status', data_get(json_decode($content, true, flags: JSON_THROW_ON_ERROR), 'uri'));
        $this->assertStringNotContainsString('private-cookie-sentinel', $content);
    }

    public function testDeviceLoginReturnsATokenWithoutPersistingDiagnosticSecrets(): void
    {
        $user = User::factory()->create();
        Telescope::flushEntries();
        Telescope::startRecording(false);

        $response = $this->postJson('/api/v1/auth/tokens', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Privacy test',
        ])->assertCreated()->assertHeader('Cache-Control', 'no-store, private');

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseCount('telescope_entries', 0);
        $this->assertFalse(Telescope::isRecording());
    }

    #[TestWith(['/telescope'])]
    #[TestWith(['/horizon'])]
    public function testLocalDiagnosticsRequireAVerifiedAdministrator(string $path): void
    {
        $this->app->register(HorizonServiceProvider::class);
        $this->get($path)->assertForbidden();
        $ordinary = User::factory()->create();
        $this->actingAs($ordinary)->get($path)->assertForbidden();
        $admin = User::factory()->unverified()->create(['is_admin' => true]);
        $this->actingAs($admin)->get($path)->assertForbidden();
        $admin->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($admin)->get($path)->assertOk();
    }

    public function testOrdinaryPersonalReadsAndWritesNeverCapturePayloadsOrQueries(): void
    {
        $owner = User::factory()->create(['name' => 'Private identity sentinel']);
        $bike = Motorcycle::factory()->for($owner)->create();
        $this->actingAs($owner);
        foreach (['/api/v1/user', '/api/v1/motorcycles', "/api/v1/motorcycles/{$bike->id}"] as $path) {
            Telescope::flushEntries();
            Telescope::startRecording(false);
            $this->getJson($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $this->assertDatabaseCount('telescope_entries', 0);
            $this->assertFalse(Telescope::isRecording());
        }
        Telescope::flushEntries();
        Telescope::startRecording(false);
        $this->postJson("/api/v1/motorcycles/{$bike->id}/maintenance-records", [
            'title' => 'Private service sentinel',
            'performed_on' => '2025-01-01',
            'odometer_km' => $bike->odometer_km,
            'notes' => 'Private maintenance prose sentinel',
        ])->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('telescope_entries', 0);
        $this->assertFalse(Telescope::isRecording());
    }

    #[TestWith(['/api/v1/auth/native/exchange'])]
    #[TestWith(['/api/v1/account/two-factor'])]
    #[TestWith(['/api/v1/account/social/link'])]
    #[TestWith(['/login'])]
    #[TestWith(['/user/confirm-password'])]
    #[TestWith(['/user/two-factor-recovery-codes'])]
    #[TestWith(['/auth/google/callback'])]
    #[TestWith(['/admin/login'])]
    #[TestWith(['/livewire/update'])]
    public function testSensitiveEndpointsSuppressCaptureEvenWhenTheRequestFails(string $path): void
    {
        Telescope::flushEntries();
        Telescope::startRecording(false);

        $this->postJson($path, ['recovery_code' => 'private-recovery-sentinel']);

        $this->assertFalse(Telescope::isRecording());
        $this->assertDatabaseCount('telescope_entries', 0);
    }
}
