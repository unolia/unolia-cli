<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Issue;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Terminal;
use Unolia\Cli\Api\Requests\Issues\ShowIssue;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesIssues;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

/**
 * One issue, read top to bottom: what is wrong, on what, the explanation,
 * what the fix would do, and the command that does it.
 */
final class ViewCommand extends BaseCommand
{
    use ResolvesIssues;

    /** The page sits two spaces in, like the tables. */
    private const MARGIN = '  ';

    /** Prose is wrapped here at most; a narrower terminal wraps sooner. */
    private const MAX_WIDTH = 84;

    protected function canonical(): string
    {
        return 'issue:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one issue');
    }

    protected function define(): void
    {
        $this->addArgument('issue', InputArgument::REQUIRED, 'Issue id, or the six characters unolia issue list prints');
    }

    public function examples(): array
    {
        return [
            'One issue' => 'unolia issue view 8d0e1f',
            'Its fix metadata' => 'unolia issue view 8d0e1f --json fix',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $issue = $this->fetch(new ShowIssue($this->issueId((string) $this->argumentString('issue'))));

        if ($this->structured()) {
            $this->out()->record($issue);

            return ExitCode::Ok;
        }

        $this->out()->formatted(implode("\n", $this->page($issue)));

        return ExitCode::Ok;
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return list<string>
     */
    private function page(array $issue): array
    {
        $short = Str::shortId($issue['id'] ?? null);
        $state = Str::scalar($issue['state'] ?? null, 'open');
        $width = min(self::MAX_WIDTH, max(40, (new Terminal)->getWidth() - 2 * mb_strlen(self::MARGIN)));

        // Headline: the glyph in the severity colour, the check title in bold.
        $lines = [''];
        $lines[] = self::MARGIN.Status::severity($issue['severity'] ?? null)->styled(false).' '.Cell::text(Str::scalar($issue['check_title'] ?? ($issue['check'] ?? null), 'Issue'))->bold()->styled(false);

        // One dim line of facts: severity, state (with when it was fixed), what it is about, where, the short id.
        $facts = [Status::severityWord($issue['severity'] ?? null)->styled(false)];
        // Open is the normal state of an issue, so it stays plain; fixed is the good news.
        $facts[] = match ($state) {
            'fixed' => Cell::text('fixed'.(is_string($issue['fixed_at'] ?? null) ? ' '.RelativeTime::ago($issue['fixed_at']) : ''))->color('green')->styled(false),
            'open' => 'open',
            default => Cell::text($state)->dim()->styled(false),
        };

        foreach ([Arr::get($issue, 'concern.name'), Arr::get($issue, 'project.name')] as $fact) {
            if (is_string($fact) && $fact !== '') {
                $facts[] = OutputFormatter::escape($fact);
            }
        }

        $type = Arr::get($issue, 'concern.type');
        $facts[] = Cell::text(trim((is_string($type) ? $type.' ' : '').'#'.$short))->dim()->styled(false);
        $lines[] = self::MARGIN.implode(' <fg=gray>·</> ', array_filter($facts, static fn (string $fact): bool => $fact !== ''));

        $message = $issue['message'] ?? null;

        if (is_string($message) && trim($message) !== '') {
            $lines[] = '';

            foreach (Str::wrap($message, $width) as $line) {
                $lines[] = self::MARGIN.OutputFormatter::escape($line);
            }
        }

        array_push($lines, '', ...$this->fixBlock($issue, $short, $state));

        $lines[] = '';
        $lines[] = self::MARGIN.'<fg=gray>First seen '.RelativeTime::ago(is_string($issue['first_detected_at'] ?? null) ? $issue['first_detected_at'] : null)
            .(is_string($issue['url'] ?? null) ? ' · '.$this->out()->link($issue['url'], 'open in the browser') : '').'</>';
        $lines[] = '';

        return $lines;
    }

    /**
     * What the fix would do, how safe it is, and the exact record changes when
     * the API proposes them. Then the command to run, or why there is none.
     *
     * @param  array<string, mixed>  $issue
     * @return list<string>
     */
    private function fixBlock(array $issue, string $short, string $state): array
    {
        $indent = self::MARGIN.'     ';
        $name = Arr::get($issue, 'fix.name');

        if (Arr::get($issue, 'fix.available') !== true || ! is_string($name)) {
            return [self::MARGIN.'<fg=gray>FIX</>  <fg=gray>no automatic fix; '.($state === 'open' ? 'this one is done by hand, then unolia issue recheck '.$short.' clears it' : 'nothing left to do').'</>'];
        }

        $lines = [self::MARGIN.'<fg=gray>FIX</>  '.Cell::text($name)->bold()->styled(false)];

        $traits = array_filter([
            is_string(Arr::get($issue, 'fix.blast_radius')) ? Arr::get($issue, 'fix.blast_radius').' blast radius' : '',
            Arr::get($issue, 'fix.reversible') === true ? 'reversible' : (Arr::get($issue, 'fix.reversible') === false ? 'not reversible' : ''),
            is_string(Arr::get($issue, 'fix.required_ability')) ? 'needs '.Arr::get($issue, 'fix.required_ability') : '',
        ], static fn (string $trait): bool => $trait !== '');

        if ($traits !== []) {
            $lines[] = $indent.'<fg=gray>'.OutputFormatter::escape(implode(' · ', $traits)).'</>';
        }

        foreach (['create' => ['+', 'green'], 'update' => ['~', 'yellow'], 'delete' => ['-', 'red']] as $operation => [$sign, $color]) {
            $records = Arr::get($issue, 'data.proposal.'.$operation);

            foreach (is_array($records) ? $records : [] as $record) {
                if (! is_array($record)) {
                    continue;
                }

                $value = Str::scalar($record['value'] ?? null, '');
                $lines[] = $indent.sprintf(
                    '<fg=%s>%s</> %s  %s%s',
                    $color,
                    $sign,
                    Cell::text(Str::scalar($record['type'] ?? null, ''))->dim()->styled(false),
                    OutputFormatter::escape(Str::scalar($record['name'] ?? null, '')),
                    $value === '' ? '' : '  '.OutputFormatter::escape(Str::limit($value, 60)),
                );
            }
        }

        if ($state !== 'open') {
            return $lines;
        }

        $lines[] = '';
        $lines[] = self::MARGIN.'<fg=gray>Preview with</> <fg=cyan>unolia issue fix '.$short.' --dry-run</><fg=gray>, apply with</> <fg=cyan>unolia issue fix '.$short.'</>';

        return $lines;
    }
}
