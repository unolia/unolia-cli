<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Assert;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Unolia\Cli\Api\Client;
use Unolia\Cli\Api\ClientFactory;
use Unolia\Cli\Api\Poller;
use Unolia\Cli\Application;
use Unolia\Cli\Console\Ask;
use Unolia\Cli\Console\Face;
use Unolia\Cli\Console\Out;
use Unolia\Cli\Context\GitRemote;
use Unolia\Cli\Context\ProjectConfig;
use Unolia\Cli\Local\ComposerLock;
use Unolia\Cli\Local\Herd;
use Unolia\Cli\Local\Node;
use Unolia\Cli\Local\Php;
use Unolia\Cli\Mcp\AgentCli;
use Unolia\Cli\Runtime;
use Unolia\Cli\Support\Browser;
use Unolia\Cli\Support\Notifier;
use Unolia\Cli\Support\Stdin;

/**
 * Runs the real Application against canned answers, in either face.
 */
final class CliTester
{
    private FakeApi $api;

    private bool $interactive = false;

    /** @var array<string, mixed> */
    private array $answers = [];

    /** @var array<string, string> */
    private array $env = [];

    /** @var array<string, string|null> */
    private array $git = [];

    /** @var array<class-string, object> */
    private array $services = [];

    private string $stdin = '';

    private ?Runtime $runtime = null;

    public readonly FakeBrowser $browser;

    public readonly FakeNotifier $notifier;

    private function __construct(public readonly TempHome $home)
    {
        $this->api = FakeApi::make();
        $this->browser = new FakeBrowser;
        $this->notifier = new FakeNotifier;
    }

    public static function make(): self
    {
        return new self(new TempHome);
    }

    public function interactive(bool $on = true): self
    {
        $this->interactive = $on;

        return $this;
    }

    public function withApi(FakeApi $api): self
    {
        $this->api = $api;

        return $this;
    }

    public function api(): FakeApi
    {
        return $this->api;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function withConfig(array $config): self
    {
        $this->home->write('.unolia/config.json', (string) json_encode($config, JSON_PRETTY_PRINT)."\n");

        return $this;
    }

    public function withGitRemote(string $url = 'git@github.com:acme/marketing.git', string $branch = 'main'): self
    {
        $this->git = [
            'root' => $this->home->cwd,
            'remote' => $url,
            'branch' => $branch,
            'commit' => '3f9c2e1',
        ];

        return $this;
    }

    public function withToken(string $token = 'test-token', string $kind = 'user', string $name = 'eser', string $host = 'app.unolia.com'): self
    {
        $file = $this->home->home.'/.config/unolia/hosts.json';

        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0700, true);
        }

        file_put_contents($file, (string) json_encode([
            $host => ['token' => $token, 'kind' => $kind, 'name' => $name, 'created_at' => '2026-09-05T09:00:00Z'],
        ], JSON_PRETTY_PRINT));
        chmod($file, 0600);

        return $this;
    }

    public function withoutToken(): self
    {
        @unlink($this->home->home.'/.config/unolia/hosts.json');

        return $this;
    }

    /**
     * @param  array<string, string>  $vars
     */
    public function env(array $vars): self
    {
        $this->env = array_merge($this->env, $vars);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $answers  question fragment => answer
     */
    public function answers(array $answers): self
    {
        $this->answers = array_merge($this->answers, $answers);
        $this->interactive = true;

        return $this;
    }

    public function stdin(string $contents): self
    {
        $this->stdin = $contents;

        return $this;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $id
     * @param  T  $service
     */
    public function withService(string $id, object $service): self
    {
        $this->services[$id] = $service;

        return $this;
    }

    public function withHerd(FakeHerd $herd): self
    {
        return $this->withService(Herd::class, $herd);
    }

    public function withPhp(?string $version): self
    {
        return $this->withService(Php::class, new FakePhp($version));
    }

    /**
     * @param  array<string, string|null>  $packages
     */
    public function withComposer(array $packages, ?string $composer = '2.8.4'): self
    {
        return $this->withService(ComposerLock::class, new FakeComposerLock($packages, $composer));
    }

    public function withNode(?string $version): self
    {
        return $this->withService(Node::class, new FakeNode($version));
    }

    public function runtime(): Runtime
    {
        return $this->runtime ?? throw new \RuntimeException('Run a command first.');
    }

    public function run(string ...$argv): CliResult
    {
        $runtime = $this->buildRuntime($argv);
        $output = new BufferedConsoleOutput;
        $application = new Application($runtime);

        $exitCode = $application->run(new ArgvInput(['unolia', ...$argv]), $output);

        $this->runtime = $runtime;

        $failure = $this->api->failure();

        if ($failure !== null) {
            Assert::fail($failure);
        }

        return new CliResult($exitCode, $output->fetch(), $output->errors());
    }

    /**
     * @param  list<string>  $argv
     */
    private function buildRuntime(array $argv): Runtime
    {
        $env = array_merge([
            'HOME' => $this->home->home,
            'XDG_CONFIG_HOME' => $this->home->home.'/.config',
            'NO_COLOR' => '1',
            'UNOLIA_TESTING' => '1',
        ], $this->env);

        $runtime = Runtime::fromEnvironment($env, $this->home->cwd);

        $face = Face::detect(
            new ArgvInput(['unolia', ...$argv]),
            new BufferedOutput,
            $env,
        )->withInteractive($this->interactive);

        $runtime->set(Face::class, $face);
        $runtime->set(Ask::class, new ScriptedAsk($face, $this->answers, static fn (): Out => $runtime->out()));
        $runtime->set(ClientFactory::class, new FakeClientFactory($runtime, $this->api));
        $runtime->factory(Client::class, static function (Runtime $runtime): Client {
            $host = $runtime->host();
            $kind = $runtime->hosts()->entry($host)['kind'] ?? 'unknown';

            return $runtime->clients()->make($host, $runtime->hosts()->tokenFor($host), is_string($kind) ? $kind : 'unknown');
        });
        $runtime->set(GitRemote::class, new FakeGitRemote($this->home->cwd, $this->git));
        $runtime->set(ProjectConfig::class, ProjectConfig::discover($this->home->cwd, $this->home->cwd));
        $runtime->set(Poller::class, new InstantPoller);
        $runtime->set(Browser::class, $this->browser);
        $runtime->set(Notifier::class, $this->notifier);
        $runtime->set(Stdin::class, new FakeStdin($this->stdin));
        $runtime->set(Herd::class, new FakeHerd($this->home->cwd));
        $runtime->set(AgentCli::class, new FakeAgentCli);

        foreach ($this->services as $id => $service) {
            $runtime->set($id, $service);
        }

        return $runtime;
    }
}
