<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

use Unolia\Cli\Api\Client;
use Unolia\Cli\Api\Requests\Deployments\DeploymentOutput;
use Unolia\Cli\Api\Requests\Deployments\ShowDeployment;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;

/**
 * A deployment, followed through the resource itself and its output cursor.
 */
final class DeploymentTarget implements Target
{
    public const TERMINAL = ['success', 'failed', 'cancelled', 'error', 'timed_out', 'skipped'];

    private int $offset = 0;

    private bool $first = true;

    /** The tail of the last chunk that did not end with a newline, waiting for its rest. */
    private string $partial = '';

    public function __construct(
        private readonly Client $api,
        private readonly int $id,
        private readonly int $wait = 20,
        private readonly bool $withOutput = true,
    ) {}

    public function fetch(): TargetState
    {
        $wait = $this->first ? 0 : $this->wait;
        $this->first = false;

        $body = $this->api->send(new ShowDeployment($this->id, ['wait' => $wait]))->json();
        $body = is_array($body) ? $body : [];

        $data = $body['data'] ?? [];
        $meta = $body['meta'] ?? [];
        $chunk = '';

        if ($this->withOutput && Arr::get(is_array($data) ? $data : [], 'output.available') === true) {
            $output = $this->api->send(new DeploymentOutput($this->id, ['after' => $this->offset]))->json('data');

            if (is_array($output)) {
                $chunk = is_string($output['chunk'] ?? null) ? $output['chunk'] : '';
                $next = $output['next_offset'] ?? null;

                if (is_numeric($next)) {
                    $this->offset = (int) $next;
                }
            }
        }

        /** @var array<string, mixed> $data */
        $data = is_array($data) ? $data : [];

        /** @var array<string, mixed> $meta */
        $meta = is_array($meta) ? $meta : [];

        return new TargetState($data, $meta, ['chunk' => $chunk]);
    }

    public function events(?TargetState $previous, TargetState $state): iterable
    {
        if ($previous === null) {
            yield new WatchEvent('deployment.started', [
                'deployment' => $state->int('id'),
                'status' => $state->string('status'),
                'website' => $state->get('website.domain'),
                'commit' => $state->get('commit.short'),
                'branch' => $state->get('commit.branch'),
            ], sprintf(
                'Deployment %s is %s',
                (string) $state->int('id'),
                (string) $state->string('status', 'pending'),
            ));
        }

        foreach ($this->lines($state) as $line) {
            yield new WatchEvent('deployment.output', ['line' => $line], $line);
        }

        if ($previous !== null && $previous->string('status') !== $state->string('status') && ! $this->isDone($state)) {
            yield new WatchEvent('deployment.status', [
                'deployment' => $state->int('id'),
                'status' => $state->string('status'),
            ], sprintf('Status %s', (string) $state->string('status', 'unknown')));
        }

        if ($this->isDone($state)) {
            yield new WatchEvent('deployment.finished', [
                'deployment' => $state->int('id'),
                'status' => $state->string('status'),
                'duration_seconds' => $state->int('duration_seconds'),
                'url' => $state->string('url'),
            ], $this->summary($state));
        }
    }

    public function isDone(TargetState $state): bool
    {
        return in_array((string) $state->string('status', ''), self::TERMINAL, true);
    }

    /**
     * The complete lines this reading adds. The output is read by byte offset,
     * so a chunk can end in the middle of a line. That tail is kept back and
     * prepended to the next chunk, and only flushed as it stands once the
     * deployment is over and nothing more will arrive.
     *
     * @return list<string>
     */
    private function lines(TargetState $state): array
    {
        $chunk = $state->extra['chunk'] ?? '';
        $text = $this->partial.(is_string($chunk) ? $chunk : '');
        $this->partial = '';

        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\R/', $text) ?: [];

        // A text that ends with a newline splits into a trailing empty
        // string; one that does not ends with the partial line.
        $tail = (string) array_pop($lines);

        if ($tail !== '') {
            if ($this->isDone($state)) {
                $lines[] = $tail;
            } else {
                $this->partial = $tail;
            }
        }

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    public function exitCode(TargetState $state): ExitCode
    {
        return $state->string('status') === 'success' ? ExitCode::Ok : ExitCode::RemoteFailure;
    }

    public function summary(TargetState $state): string
    {
        $duration = $state->int('duration_seconds');

        return sprintf(
            'Deployment %s %s%s',
            (string) $state->int('id'),
            (string) $state->string('status', 'finished'),
            $duration === null ? '' : ' in '.RelativeTime::duration($duration),
        );
    }

    public function header(TargetState $state): string
    {
        $parts = array_filter([
            $state->get('website.domain'),
            $state->get('commit.branch'),
            $state->get('commit.short'),
        ], is_string(...));

        $name = 'Deployment #'.(string) $state->int('id');

        return $parts === [] ? $name : $name.' · '.implode(' · ', $parts);
    }
}
