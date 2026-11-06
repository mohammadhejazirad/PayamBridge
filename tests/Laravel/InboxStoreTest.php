<?php

declare(strict_types=1);

namespace PayamBridge\Tests\Laravel;

use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use PayamBridge\Contracts\Provider;
use PayamBridge\Contracts\Surface;
use PayamBridge\Data\ConnectionId;
use PayamBridge\Drivers\Bale\BaleBot;
use PayamBridge\Http\HttpTransport;
use PayamBridge\Laravel\PayamBridgeServiceProvider;
use PayamBridge\Runtime\FileInboxStore;
use PayamBridge\Runtime\InboxStore;
use PayamBridge\Runtime\Poller;
use PayamBridge\Runtime\SqliteInboxStore;
use PayamBridge\Security\Credential;
use PayamBridge\Testing\FakeHttpClient;

final class InboxStoreTest extends TestCase
{
    private string $inboxDirectory;

    protected function getPackageProviders($app): array
    {
        return [PayamBridgeServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->inboxDirectory = sys_get_temp_dir().'/payambridge-laravel-'.bin2hex(random_bytes(8));
        $this->app['config']->set('payambridge.inbox.paths.sqlite', $this->inboxDirectory.'/inbox.sqlite');
        $this->app['config']->set('payambridge.inbox.paths.file', $this->inboxDirectory.'/inbox.txt');
    }

    protected function tearDown(): void
    {
        foreach (['inbox.sqlite', 'inbox.txt', 'inbox.txt.lock'] as $file) {
            @unlink($this->inboxDirectory.'/'.$file);
        }
        @rmdir($this->inboxDirectory);
        parent::tearDown();
    }

    public function test_provider_boot_does_not_create_inbox_and_default_is_sqlite(): void
    {
        self::assertSame('sqlite', $this->app['config']->get('payambridge.inbox.driver'));
        self::assertDirectoryDoesNotExist($this->inboxDirectory);
        self::assertInstanceOf(SqliteInboxStore::class, $this->app->make(InboxStore::class));
        self::assertFileExists($this->inboxDirectory.'/inbox.sqlite');
        self::assertFileDoesNotExist($this->inboxDirectory.'/inbox.txt');
        self::assertSame(0700, fileperms($this->inboxDirectory) & 0777);
        self::assertSame(0600, fileperms($this->inboxDirectory.'/inbox.sqlite') & 0777);
    }

    public function test_file_driver_initializes_only_file_store_and_poller_resumes(): void
    {
        $this->app['config']->set('payambridge.inbox.driver', 'file');
        $store = $this->app->make(InboxStore::class);
        self::assertInstanceOf(FileInboxStore::class, $store);
        self::assertFileExists($this->inboxDirectory.'/inbox.txt');
        self::assertFileDoesNotExist($this->inboxDirectory.'/inbox.sqlite');
        self::assertSame($store, $this->app->make(InboxStore::class));

        $connection = new ConnectionId(Provider::Bale, Surface::Bot, 'orders');
        $body = '{"ok":true,"result":[{"update_id":1,"future_event":{}}]}';
        $fake = new FakeHttpClient([new Response(200, [], $body), new Response(200, [], $body)]);
        $driver = new BaleBot($connection, new Credential('test-token'), new HttpTransport($fake));
        self::assertSame(1, $this->app->make(Poller::class)->once($driver));
        $this->app->forgetScopedInstances();
        self::assertSame(0, $this->app->make(Poller::class)->once($driver));
        self::assertSame(['limit' => 100, 'offset' => 2], json_decode((string) $fake->requests()[1]->getBody(), true));
        $reopened = $this->app->make(InboxStore::class);
        self::assertInstanceOf(FileInboxStore::class, $reopened);
        self::assertSame(['1'], array_column($reopened->events($connection), 'event_id'));
        self::assertFileDoesNotExist($this->inboxDirectory.'/inbox.sqlite');
    }

    public function test_invalid_driver_or_path_does_not_create_storage(): void
    {
        $this->app['config']->set('payambridge.inbox.driver', 'mysql');
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->app->make(InboxStore::class);
        } finally {
            self::assertDirectoryDoesNotExist($this->inboxDirectory);
        }
    }

    public function test_invalid_selected_path_does_not_create_storage(): void
    {
        $this->app['config']->set('payambridge.inbox.driver', 'file');
        $this->app['config']->set('payambridge.inbox.paths.file', false);
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->app->make(InboxStore::class);
        } finally {
            self::assertDirectoryDoesNotExist($this->inboxDirectory);
        }
    }

    public function test_invalid_lease_is_rejected_before_inbox_creation(): void
    {
        $this->app['config']->set('payambridge.inbox.driver', 'file');
        $this->app['config']->set('payambridge.inbox.lease_seconds', 0);
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->app->make(Poller::class);
        } finally {
            self::assertDirectoryDoesNotExist($this->inboxDirectory);
        }
    }

    public function test_existing_user_inbox_binding_is_preserved(): void
    {
        $custom = $this->createStub(InboxStore::class);
        $this->app->instance(InboxStore::class, $custom);
        (new PayamBridgeServiceProvider($this->app))->register();
        self::assertSame($custom, $this->app->make(InboxStore::class));
        self::assertInstanceOf(Poller::class, $this->app->make(Poller::class));
        self::assertDirectoryDoesNotExist($this->inboxDirectory);
    }

    public function test_existing_user_poller_binding_is_preserved(): void
    {
        $custom = new Poller($this->createStub(InboxStore::class));
        $this->app->instance(Poller::class, $custom);
        (new PayamBridgeServiceProvider($this->app))->register();
        self::assertSame($custom, $this->app->make(Poller::class));
        self::assertDirectoryDoesNotExist($this->inboxDirectory);
    }

    public function test_config_cache_records_selected_driver(): void
    {
        $previous = getenv('PAYAMBRIDGE_INBOX_DRIVER');
        $previousEnv = $_ENV['PAYAMBRIDGE_INBOX_DRIVER'] ?? null;
        $previousServer = $_SERVER['PAYAMBRIDGE_INBOX_DRIVER'] ?? null;
        try {
            $this->artisan('vendor:publish', ['--tag' => 'payambridge-config', '--force' => true])->assertExitCode(0);
            foreach (['file', 'sqlite'] as $driver) {
                putenv('PAYAMBRIDGE_INBOX_DRIVER='.$driver);
                $_ENV['PAYAMBRIDGE_INBOX_DRIVER'] = $driver;
                $_SERVER['PAYAMBRIDGE_INBOX_DRIVER'] = $driver;
                $this->artisan('config:cache')->assertExitCode(0);
                $cached = require $this->app->getCachedConfigPath();
                self::assertSame($driver, $cached['payambridge']['inbox']['driver']);
                $extension = $driver === 'file' ? 'txt' : 'sqlite';
                self::assertStringEndsWith('/inbox.'.$extension, $cached['payambridge']['inbox']['paths'][$driver]);
            }
            self::assertDirectoryDoesNotExist($this->inboxDirectory);
        } finally {
            if ($previous === false) {
                putenv('PAYAMBRIDGE_INBOX_DRIVER');
            } else {
                putenv('PAYAMBRIDGE_INBOX_DRIVER='.$previous);
            }
            if ($previousEnv === null) {
                unset($_ENV['PAYAMBRIDGE_INBOX_DRIVER']);
            } else {
                $_ENV['PAYAMBRIDGE_INBOX_DRIVER'] = $previousEnv;
            }
            if ($previousServer === null) {
                unset($_SERVER['PAYAMBRIDGE_INBOX_DRIVER']);
            } else {
                $_SERVER['PAYAMBRIDGE_INBOX_DRIVER'] = $previousServer;
            }
            $this->artisan('config:clear')->assertExitCode(0);
            @unlink(config_path('payambridge.php'));
        }
    }
}
