<?php

declare(strict_types=1);

namespace Unolia\Cli\Command\Domain;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Unolia\Cli\Api\ApiException;
use Unolia\Cli\Api\Requests\Domains\ListDomainRecords;
use Unolia\Cli\Api\Requests\Domains\ShowDomain;
use Unolia\Cli\Api\Requests\Issues\ListIssues;
use Unolia\Cli\Command\BaseCommand;
use Unolia\Cli\Command\Concerns\ResolvesZones;
use Unolia\Cli\Console\ExitCode;
use Unolia\Cli\Console\Table\Cell;
use Unolia\Cli\Console\Table\Status;
use Unolia\Cli\Console\Table\Tint;
use Unolia\Cli\Support\Arr;
use Unolia\Cli\Support\RelativeTime;
use Unolia\Cli\Support\Str;

/**
 * One zone as a page: who hosts it, whether the nameservers agree, what the
 * records add up to, what is open about it, and the commands that go deeper.
 */
final class ViewCommand extends BaseCommand
{
    use ResolvesZones;

    private const MARGIN = '  ';

    protected function canonical(): string
    {
        return 'domain:view';
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show one DNS zone');
    }

    protected function define(): void
    {
        $this->addArgument('zone', InputArgument::OPTIONAL, 'The zone, the project\'s one by default');
        $this->addOption('zone', null, InputOption::VALUE_REQUIRED, 'Same as the argument');
    }

    public function examples(): array
    {
        return [
            'This project\'s zone' => 'unolia domain view',
            'Another one' => 'unolia domain view acme.com',
            'Its nameservers' => 'unolia domain view acme.com --json nameservers',
        ];
    }

    protected function handle(InputInterface $input): ExitCode
    {
        $zone = $this->zone($this->argumentString('zone'));
        $domain = $this->fetch(new ShowDomain($zone));

        if ($this->structured()) {
            $this->out()->record($domain);

            return ExitCode::Ok;
        }

        $this->out()->formatted(implode("\n", $this->page($zone, $domain)));

        return ExitCode::Ok;
    }

    /**
     * @param  array<string, mixed>  $domain
     * @return list<string>
     */
    private function page(string $zone, array $domain): array
    {
        $ok = Arr::get($domain, 'nameservers.ok');
        $glyph = match ($ok) {
            true => Cell::text('●')->color('green'),
            false => Cell::text('✕')->color('red'),
            default => Cell::text('·')->dim(),
        };

        $lines = ['', self::MARGIN.$glyph->styled(false).' '.Cell::text($zone)->bold()->styled(false).'  '.$this->out()->link('https://'.$zone, '<fg=gray>open the site</>')];

        $facts = array_filter([
            Cell::text(Str::scalar(Arr::get($domain, 'provider.label') ?? Arr::get($domain, 'provider.slug'), ''))->color(Tint::provider(Arr::get($domain, 'provider.slug')))->styled(false),
            is_string(Arr::get($domain, 'project.name')) ? OutputFormatter::escape(Arr::get($domain, 'project.name')) : Cell::text('no project')->dim()->styled(false),
            is_string(Arr::get($domain, 'team.name')) ? OutputFormatter::escape(Arr::get($domain, 'team.name')) : '',
            is_numeric($domain['default_ttl'] ?? null) ? Cell::text('default TTL '.RelativeTime::duration((int) $domain['default_ttl']))->dim()->styled(false) : '',
            Cell::text('synced '.RelativeTime::ago(is_string($domain['synced_at'] ?? null) ? $domain['synced_at'] : null, null, 'never'))->dim()->styled(false),
        ], static fn (string $part): bool => $part !== '');
        $lines[] = self::MARGIN.implode(' <fg=gray>·</> ', $facts);

        // Nameservers: the expected list, and the observed one only when it differs.
        $expected = Arr::get($domain, 'nameservers.expected');
        $observed = Arr::get($domain, 'nameservers.observed');
        $lines[] = '';
        $lines[] = self::MARGIN.'<fg=gray>NAMESERVERS</>  '.match ($ok) {
            true => '<fg=green>the world points at the provider</>',
            false => '<fg=red>the world points elsewhere</>',
            default => '<fg=gray>not checked yet</>',
        };

        foreach (is_array($expected) ? $expected : [] as $nameserver) {
            $lines[] = self::MARGIN.'             '.OutputFormatter::escape(Str::scalar($nameserver, ''));
        }

        if ($ok === false && is_array($observed)) {
            $lines[] = self::MARGIN.'             <fg=gray>observed:</> <fg=red>'.OutputFormatter::escape(implode(', ', array_map(static fn (mixed $ns): string => Str::scalar($ns, ''), $observed))).'</>';
        }

        // Records, summed by type.
        $counts = $this->recordCounts($zone);

        if ($counts !== null) {
            $total = array_sum($counts);
            $parts = [];

            foreach ($counts as $type => $count) {
                $color = Tint::recordType($type);
                $parts[] = ($color === null ? Cell::text($type)->dim() : Cell::text($type)->color($color))->styled(false).' '.$count;
            }

            $lines[] = '';
            $lines[] = self::MARGIN.'<fg=gray>RECORDS</>      '.($total === 1 ? '1 record' : $total.' records').($parts === [] ? '' : ' <fg=gray>·</> '.implode(' <fg=gray>·</> ', $parts));
        }

        // Open issues about the zone or its records.
        $issues = $this->issues($zone);

        if ($issues !== null) {
            $lines[] = '';
            $lines[] = self::MARGIN.'<fg=gray>ISSUES</>       '.($issues === [] ? '<fg=gray>none open</>' : '');

            foreach (array_slice($issues, 0, 5) as $issue) {
                $lines[] = self::MARGIN.'             '.Status::severity($issue['severity'] ?? null)->styled(false).' '.OutputFormatter::escape(Str::scalar($issue['check_title'] ?? ($issue['check'] ?? null), 'Issue')).' <fg=gray>#'.Str::shortId($issue['id'] ?? null).'</>';
            }

            if (count($issues) > 5) {
                $lines[] = self::MARGIN.'             <fg=gray>and '.(count($issues) - 5).' more, unolia issues --domain '.$zone.'</>';
            }
        }

        $lines[] = '';
        $lines[] = self::MARGIN.'<fg=gray>Records with</> <fg=cyan>unolia dns '.$zone.'</><fg=gray>, live answers with</> <fg=cyan>unolia dns check '.$zone.'</>';
        $lines[] = '';

        return $lines;
    }

    /**
     * @return array<string, int>|null
     */
    private function recordCounts(string $zone): ?array
    {
        try {
            $rows = $this->collection(new ListDomainRecords($zone, ['per_page' => 100]));
        } catch (ApiException) {
            return null;
        }

        $counts = [];

        foreach ($rows as $row) {
            $type = strtoupper(Str::scalar($row['type'] ?? null, '?'));
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function issues(string $zone): ?array
    {
        try {
            return $this->collection(new ListIssues(['domain' => $zone, 'per_page' => 20]));
        } catch (ApiException) {
            return null;
        }
    }
}
