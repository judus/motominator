<?php

namespace Tests\Feature;

use App\Logging\ConfigureLogging;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Laravel\Pail\File;
use Laravel\Pail\Files;
use Laravel\Pail\Handler;
use Mockery;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Tests\TestCase;

class ApplicationLoggingTest extends TestCase
{
    public function testQueueExecutionKeepsTheDispatchTraceAndAddsAJobIdentifier(): void
    {
        Context::add('trace_id', '83a1cc75-c18e-4a5d-8204-1fc0a230023b');
        $log = Log::spy();

        Bus::dispatchSync(new LoggingProbeJob());

        $log->shouldHaveReceived('info')->with(
            'queue.probe',
            Mockery::on(
                fn (array $context): bool => $context['trace_id'] === '83a1cc75-c18e-4a5d-8204-1fc0a230023b'
                    && is_string(
                        $context['job_id']
                    ) && $context['job_id'] !== ''
            )
        );
        $log->shouldHaveReceived('info')->with('queue.job_completed', Mockery::type('array'));
        $this->assertFalse(Context::has('job_id'));
    }

    public function testHttpRequestsReceiveDistinctServerGeneratedTraceIds(): void
    {
        $first = $this->withHeader('X-Request-ID', 'untrusted-id')->getJson('/up');
        $second = $this->getJson('/up');

        $first->assertHeader('X-Request-ID');
        $this->assertNotSame('untrusted-id', $first->headers->get('X-Request-ID'));
        $this->assertNotSame($first->headers->get('X-Request-ID'), $second->headers->get('X-Request-ID'));
        $this->assertFalse(Context::has('trace_id'));
    }

    public function testJsonLogsRedactNestedSecretsAndExceptionArgumentsWithoutDumpingObjects(): void
    {
        config(['logging.json' => true]);
        $stream = fopen('php://memory', 'w+');
        $this->assertIsResource($stream);
        $handler = new StreamHandler($stream);
        $logger = new Logger(new MonologLogger('test', [$handler]));
        (new ConfigureLogging())($logger);

        $logger->error('Failed: Authorization: Bearer private-bearer api_key=private-inline', [
            'password' => 'private-password',
            'nested' => ['api_key' => 'private-key', 'cookie' => 'private-cookie'],
            'exception' => new \RuntimeException(
                'api_key=private-exception'
            ),
            'object' => (object) ['key' => 'private-object'],
            'user_id' => 42,
        ]);
        rewind($stream);
        $output = stream_get_contents($stream);
        $record = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('private-', $output);
        $this->assertSame('[REDACTED]', data_get($record, 'context.nested.api_key'));
        $this->assertSame(42, data_get($record, 'context.user_id'));
        $this->assertSame(\RuntimeException::class, data_get($record, 'context.exception.class'));
        $this->assertSame('[OBJECT stdClass]', data_get($record, 'context.object'));
        fclose($stream);
    }

    public function testExceptionLoggingOmitsPrivateSqlAndPreviousMessagesButKeepsDiagnosis(): void
    {
        config(['logging.json' => true]);
        $stream = fopen('php://memory', 'w+');
        $this->assertIsResource($stream);
        $logger = new Logger(new MonologLogger('test', [new StreamHandler($stream)]));
        (new ConfigureLogging())($logger);
        $cause = new \RuntimeException('Private prose sentinel in underlying driver');
        $exception = new QueryException('mysql', 'insert into notes values (?)', ['Private prose sentinel'], $cause);
        $logger->withContext(['trace_id' => 'safe-trace']);
        $this->app->instance('log', $logger);
        report($exception);
        rewind($stream);
        $output = stream_get_contents($stream);
        $record = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Private prose sentinel', $output);
        $this->assertStringNotContainsString('insert into', $output);
        $this->assertSame(QueryException::class, data_get($record, 'context.exception.class'));
        $this->assertSame('safe-trace', data_get($record, 'context.trace_id'));
        $this->assertNotEmpty(data_get($record, 'context.exception.file'));
        $this->assertNotEmpty(data_get($record, 'context.exception.line'));
        fclose($stream);
    }

    public function testEveryConfiguredDatabaseConnectionMasksBoundValuesInFailures(): void
    {
        foreach (['sqlite', 'mysql', 'mariadb', 'pgsql', 'sqlsrv'] as $connection) {
            $this->assertTrue(DB::connection($connection)->getConfig('mask_bindings_in_exception_messages'));
        }
        try {
            DB::select('select * from nonexistent_privacy_test_table where note = ?', ['Private sql sentinel']);
            $this->fail('Invalid query should fail.');
        } catch (QueryException $exception) {
            $this->assertStringNotContainsString('Private sql sentinel', $exception->getMessage());
        }
    }

    public function testPailRedactsOriginalLaravelLogEvents(): void
    {
        $directory = sys_get_temp_dir() . '/motominator-pail-' . bin2hex(random_bytes(8));
        $file = new File($directory . '/test.pail');
        $file->create();
        $this->app->instance(Files::class, new Files($directory));
        $this->app->forgetInstance(Handler::class);
        try {
            $this->app->make(Handler::class)->log(
                new MessageLogged('error', 'Bearer private-bearer', ['api_key' => 'private-key'])
            );

            $exception = new QueryException(
                'mysql',
                'insert into notes values (?)',
                ['private-prose'],
                new \RuntimeException('private-driver-prose')
            );
            $this->app->make(Handler::class)->log(new MessageLogged('error', $exception->getMessage()));
            $output = $this->stringValue(file_get_contents((string) $file));
            $this->assertStringNotContainsString('private-', $output);
            $this->assertStringContainsString('[REDACTED]', $output);
        } finally {
            $file->destroy();
            unlink($directory . '/.gitignore');
            rmdir($directory);
        }
    }
}
