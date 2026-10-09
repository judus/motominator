<?php

namespace Tests\Feature;

use App\Garage\Actions\Invoices\UploadInvoice;
use App\Models\Motorcycle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceQuotaConcurrencyTest extends TestCase
{
    // Committed fixtures ensure the operation itself owns the lock, rather than a test transaction.
    use DatabaseMigrations;

    public function testAnotherConnectionCannotAllocateAccountStorageWhileUploadIsWriting(): void
    {
        Storage::fake('invoices');
        $bike = Motorcycle::factory()->create();
        $owner = $bike->user;
        $connectionName = DB::getDefaultConnection();
        config(['database.connections.quota_probe' => Config::array('database.connections.' . $connectionName)]);
        $other = DB::connection('quota_probe');
        $source = UploadedFile::fake()->image('bill.jpg');
        $file = $this->partialMock(UploadedFile::class);
        $file->__construct($source->getPathname(), 'bill.jpg', null, null, true);
        $file->shouldReceive('storeAs')->once()->andReturnUsing(function () use ($other, $owner): string {
            $other->beginTransaction();
            try {
                $other->select('select id from users where id = ? for update nowait', [$owner->id]);
                $this->fail('A concurrent allocation must not acquire this account lock.');
            } catch (QueryException $exception) {
                $this->assertSame(3572, data_get($exception->errorInfo, '1'));
            } finally {
                $other->rollBack();
            }

            return 'probe-path';
        });
        try {
            app(UploadInvoice::class)($owner, $bike, $file);
            $this->assertDatabaseCount('evidence_documents', 1);
        } finally {
            DB::purge('quota_probe');
        }
    }
}
