<?php

declare(strict_types=1);

namespace PayamBridge\Tests\Laravel;

use GuzzleHttp\Psr7\Response;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Orchestra\Testbench\TestCase;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Laravel\ConnectionManager;
use PayamBridge\Laravel\CredentialResolver;
use PayamBridge\Laravel\Facades\PayamBridge;
use PayamBridge\Laravel\Notifications\NotificationRoute;
use PayamBridge\Laravel\Notifications\PayamBridgeChannel;
use PayamBridge\Laravel\Notifications\TextMessage;
use PayamBridge\Laravel\PayamBridgeServiceProvider;
use PayamBridge\Security\Credential;
use PayamBridge\Testing\FakeHttpClient;

final class BridgeTest extends TestCase
{
    private FakeHttpClient $fake;

    protected function getPackageProviders($app): array
    {
        return [PayamBridgeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('payambridge.default', 'bale');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeHttpClient([new Response(200, [], '{"ok":true,"result":{"message_id":7,"chat":{"id":123}}}')]);
        $this->app->bind(HttpTransport::class, fn () => new HttpTransport($this->fake));
        $this->app->bind(CredentialResolver::class, static fn () => new class implements CredentialResolver
        {
            public function resolve(string $reference): Credential
            {
                return new Credential('test-token');
            }
        });
    }

    public function test_provider_config_and_scoped_manager_are_resolvable(): void
    {
        $manager = $this->app->make(ConnectionManager::class);
        self::assertSame('bale:bot:default:bale', $manager->connection()->id()->key());
        self::assertSame('rubika:bot:default:rubika', PayamBridge::connection('rubika')->id()->key());
        self::assertNotSame($manager->connection('bale'), $manager->connection('bale'));
        self::assertSame([], $this->fake->requests());
    }

    public function test_config_contains_references_and_can_be_cached(): void
    {
        try {
            $this->artisan('vendor:publish', ['--tag' => 'payambridge-config', '--force' => true])->assertExitCode(0);
            $this->artisan('config:cache')->assertExitCode(0);
            $config = require $this->app->getCachedConfigPath();
            self::assertSame('PAYAMBRIDGE_BALE_TOKEN', $config['payambridge']['connections']['bale']['token_env']);
            self::assertStringNotContainsString('test-token', file_get_contents($this->app->getCachedConfigPath()));
        } finally {
            $this->artisan('config:clear')->assertExitCode(0);
            @unlink(config_path('payambridge.php'));
        }
    }

    public function test_no_implicit_routes_prevent_route_caching(): void
    {
        try {
            $this->artisan('route:cache')->assertExitCode(0);
        } finally {
            $this->artisan('route:clear')->assertExitCode(0);
        }
    }

    public function test_notification_without_a_model_sends_to_its_explicit_connection(): void
    {
        (new AnonymousNotifiable)->route('payambridge', new NotificationRoute('bale', '123'))->notify(new BridgeNotification);
        self::assertCount(1, $this->fake->requests());
        $body = json_decode((string) $this->fake->requests()[0]->getBody(), true);
        self::assertSame(['chat_id' => '123', 'text' => 'سلام از لاراول'], $body);
    }

    public function test_queued_notification_contains_no_credential_and_resolves_it_in_the_worker(): void
    {
        $notification = new QueuedBridgeNotification;
        self::assertStringNotContainsString('test-token', serialize($notification));
        (new AnonymousNotifiable)->route('payambridge', new NotificationRoute('bale', '123'))->notify(unserialize(serialize($notification)));
        self::assertCount(1, $this->fake->requests());
    }

    public function test_database_queue_worker_sends_the_serialized_notification(): void
    {
        $this->app['config']->set('database.default', 'testing');
        $this->app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->app['config']->set('queue.default', 'database');
        $this->app['config']->set('queue.connections.database', ['driver' => 'database', 'connection' => 'testing', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90, 'after_commit' => false]);
        $this->app['db']->connection()->getSchemaBuilder()->create('jobs', static function ($table): void {
            $table->bigIncrements('id');
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        (new AnonymousNotifiable)->route('payambridge', new NotificationRoute('bale', '123'))->notify(new QueuedBridgeNotification);
        self::assertCount(0, $this->fake->requests());
        $payload = $this->app['db']->table('jobs')->value('payload');
        self::assertIsString($payload);
        self::assertStringNotContainsString('test-token', $payload);
        $this->artisan('queue:work', ['--once' => true, '--tries' => 1])->assertExitCode(0);
        self::assertCount(1, $this->fake->requests());
        self::assertSame(0, $this->app['db']->table('jobs')->count());
    }

    public function test_missing_route_does_not_send_a_message(): void
    {
        $result = $this->app->make(PayamBridgeChannel::class)->send(new AnonymousNotifiable, new BridgeNotification);
        self::assertNull($result);
        self::assertCount(0, $this->fake->requests());
    }

    public function test_capabilities_command_is_local_and_read_only(): void
    {
        $this->artisan('payam:capabilities', ['connection' => 'bale'])->expectsOutput('bale:bot:default:bale')->expectsOutput('sends_text')->expectsOutput('receives_updates')->expectsOutput('gets_identity')->expectsOutput('durable_polling')->assertExitCode(0);
        self::assertSame([], $this->fake->requests());
    }
}

class BridgeNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return [PayamBridgeChannel::class];
    }

    public function toPayamBridge(object $notifiable): TextMessage
    {
        return new TextMessage('سلام از لاراول');
    }
}

final class QueuedBridgeNotification extends BridgeNotification implements ShouldQueue
{
    use Queueable;
}
