<?php

declare(strict_types=1);

namespace Unolia\Cli;

use RuntimeException;
use Unolia\Cli\Api\AuthClient;
use Unolia\Cli\Api\Client;
use Unolia\Cli\Api\ClientFactory;
use Unolia\Cli\Api\Poller;
use Unolia\Cli\Config\Hosts;
use Unolia\Cli\Config\Paths;
use Unolia\Cli\Config\Settings;
use Unolia\Cli\Console\Ask;
use Unolia\Cli\Console\Face;
use Unolia\Cli\Console\Out;
use Unolia\Cli\Context\ContextResolver;
use Unolia\Cli\Context\GitRemote;
use Unolia\Cli\Context\ProjectConfig;
use Unolia\Cli\Local\ComposerLock;
use Unolia\Cli\Local\DotEnv;
use Unolia\Cli\Local\Herd;
use Unolia\Cli\Local\Node;
use Unolia\Cli\Local\Php;
use Unolia\Cli\Support\Browser;
use Unolia\Cli\Support\Dns;
use Unolia\Cli\Support\Notifier;

/**
 * The one place services are built. Everything is lazy, and everything can be replaced,
 * which is how the tests swap the HTTP layer and the face.
 */
final class Runtime
{
    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, callable(self): object> */
    private array $factories = [];

    /** @var array<string, true> ids set explicitly, which the Application must not overwrite */
    private array $forced = [];

    /**
     * @param  array<string, string>  $environment
     */
    private function __construct(
        private array $environment,
        private readonly string $cwd,
    ) {
        $this->registerDefaults();
    }

    /**
     * @param  array<string, string>|null  $environment
     */
    public static function fromEnvironment(?array $environment = null, ?string $cwd = null): self
    {
        return new self($environment ?? self::captureEnvironment(), $cwd ?? (getcwd() ?: '.'));
    }

    /**
     * @return array<string, string>
     */
    public static function captureEnvironment(): array
    {
        $captured = [];
        $wanted = ['CI', 'NO_COLOR', 'HOME', 'USERPROFILE', 'XDG_CONFIG_HOME', 'TERM_PROGRAM', 'EDITOR', 'PATH'];

        /** @var mixed $value */
        foreach ($_SERVER as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'UNOLIA_') || in_array($key, $wanted, true)) {
                $captured[$key] = $value;
            }
        }

        return $captured;
    }

    public function set(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
        $this->forced[$id] = true;
    }

    /** Register an instance built by the application itself, which a test may still replace. */
    public function provide(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /** True when something set this service explicitly, as a test does. */
    public function isForced(string $id): bool
    {
        return isset($this->forced[$id]);
    }

    /**
     * @param  callable(self): object  $factory
     */
    public function factory(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id], $this->forced[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $id
     * @return T
     */
    public function get(string $id): object
    {
        if (! isset($this->instances[$id])) {
            if (! isset($this->factories[$id])) {
                throw new RuntimeException(sprintf('%s is not registered on the runtime.', $id));
            }

            $this->instances[$id] = ($this->factories[$id])($this);
        }

        $instance = $this->instances[$id];

        if (! $instance instanceof $id) {
            throw new RuntimeException(sprintf('%s was registered as the wrong type.', $id));
        }

        return $instance;
    }

    public function env(string $name): ?string
    {
        $value = $this->environment[$name] ?? null;

        return $value === '' ? null : $value;
    }

    public function flag(string $name): bool
    {
        $value = $this->env($name);

        return $value !== null && $value !== '0' && strtolower($value) !== 'false';
    }

    /**
     * @return array<string, string>
     */
    public function environment(): array
    {
        return $this->environment;
    }

    /**
     * @param  array<string, string>  $environment
     */
    public function withEnvironment(array $environment): void
    {
        $this->environment = $environment;
        $this->instances = [];
        $this->forced = [];
    }

    public function cwd(): string
    {
        return $this->cwd;
    }

    public function face(): Face
    {
        return $this->get(Face::class);
    }

    public function out(): Out
    {
        return $this->get(Out::class);
    }

    public function ask(): Ask
    {
        return $this->get(Ask::class);
    }

    public function paths(): Paths
    {
        return $this->get(Paths::class);
    }

    public function hosts(): Hosts
    {
        return $this->get(Hosts::class);
    }

    public function settings(): Settings
    {
        return $this->get(Settings::class);
    }

    public function context(): ContextResolver
    {
        return $this->get(ContextResolver::class);
    }

    public function api(): Client
    {
        return $this->get(Client::class);
    }

    public function clients(): ClientFactory
    {
        return $this->get(ClientFactory::class);
    }

    public function auth(): AuthClient
    {
        return $this->get(AuthClient::class);
    }

    public function poller(): Poller
    {
        return $this->get(Poller::class);
    }

    public function host(): string
    {
        return $this->settings()->host();
    }

    public function insecure(): bool
    {
        if ($this->flag('UNOLIA_INSECURE')) {
            return true;
        }

        $host = $this->settings()->host();

        return str_ends_with($host, '.test') || str_starts_with($host, 'localhost');
    }

    private function registerDefaults(): void
    {
        $this->factory(Paths::class, fn (self $runtime): Paths => new Paths($runtime->environment()));

        $this->factory(Hosts::class, fn (self $runtime): Hosts => new Hosts($runtime->paths(), $runtime->environment()));

        $this->factory(Settings::class, fn (self $runtime): Settings => new Settings($runtime->paths(), $runtime->environment()));

        $this->factory(Face::class, fn (): Face => Face::pipe());

        $this->factory(Ask::class, fn (self $runtime): Ask => new Ask($runtime->face()));

        $this->factory(GitRemote::class, fn (self $runtime): GitRemote => new GitRemote($runtime->cwd()));

        $this->factory(ProjectConfig::class, fn (self $runtime): ProjectConfig => ProjectConfig::discover(
            $runtime->cwd(),
            $runtime->get(GitRemote::class)->root(),
        ));

        $this->factory(ContextResolver::class, fn (self $runtime): ContextResolver => new ContextResolver(
            $runtime->environment(),
            $runtime->settings(),
            $runtime->get(GitRemote::class),
            $runtime->get(ProjectConfig::class),
            $runtime->ask(),
            fn (): Client => $runtime->api(),
        ));

        $this->factory(ClientFactory::class, fn (self $runtime): ClientFactory => new ClientFactory($runtime));

        $this->factory(Client::class, function (self $runtime): Client {
            $host = $runtime->host();
            $kind = $runtime->hosts()->entry($host)['kind'] ?? 'unknown';

            return $runtime->clients()->make(
                $host,
                $runtime->hosts()->tokenFor($host),
                is_string($kind) ? $kind : 'unknown',
            );
        });

        $this->factory(AuthClient::class, fn (self $runtime): AuthClient => $runtime->clients()->auth(
            $runtime->host(),
            $runtime->hosts()->tokenFor($runtime->host()),
        ));

        $this->factory(Poller::class, fn (): Poller => new Poller);
        $this->factory(Browser::class, fn (): Browser => new Browser);
        $this->factory(Notifier::class, fn (): Notifier => new Notifier);
        $this->factory(Dns::class, fn (): Dns => new Dns);
        $this->factory(Herd::class, fn (self $runtime): Herd => new Herd($runtime->cwd()));
        $this->factory(Php::class, fn (self $runtime): Php => new Php($runtime->get(Herd::class)));
        $this->factory(ComposerLock::class, fn (): ComposerLock => new ComposerLock);
        $this->factory(Node::class, fn (): Node => new Node);
        $this->factory(DotEnv::class, fn (): DotEnv => new DotEnv);
    }
}
