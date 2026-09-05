<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The rendering mode of one invocation, decided once at boot.
 */
final readonly class Face
{
    public function __construct(
        public bool $interactive,
        public bool $color,
        public Format $format,
        public ?string $fields = null,
        public ?string $jq = null,
        public bool $yes = false,
        public bool $dryRun = false,
        public bool $paginate = false,
    ) {}

    /**
     * @param  array<string, string>  $env
     */
    public static function detect(InputInterface $input, OutputInterface $output, array $env): self
    {
        $format = Format::fromOptions($input, $env);

        $noInput = $input->hasParameterOption(['--no-interaction', '-n', '--no-input'], true)
            || ! $input->isInteractive();

        $interactive = self::isTty()
            && ! isset($env['CI'])
            && ! $noInput
            && $format === Format::Table;

        $fields = self::valueOf($input, '--json');
        $jq = self::valueOf($input, '--jq');

        return new self(
            interactive: $interactive,
            color: $output->isDecorated(),
            format: $format,
            fields: $fields,
            jq: $jq,
            yes: $input->hasParameterOption(['--yes', '-y'], true),
            dryRun: $input->hasParameterOption('--dry-run', true),
            paginate: $input->hasParameterOption('--paginate', true),
        );
    }

    /** A face for scripts, agents and tests. */
    public static function pipe(Format $format = Format::Table): self
    {
        return new self(interactive: false, color: false, format: $format);
    }

    public function withFormat(Format $format): self
    {
        return new self($this->interactive, $this->color, $format, $this->fields, $this->jq, $this->yes, $this->dryRun, $this->paginate);
    }

    public function withInteractive(bool $interactive): self
    {
        return new self($interactive, $this->color, $this->format, $this->fields, $this->jq, $this->yes, $this->dryRun, $this->paginate);
    }

    public function withYes(bool $yes): self
    {
        return new self($this->interactive, $this->color, $this->format, $this->fields, $this->jq, $yes, $this->dryRun, $this->paginate);
    }

    /**
     * The value of an option, the way Symfony's own parser reads it: the token after the
     * option counts only when it is not another option.
     */
    private static function valueOf(InputInterface $input, string $option): ?string
    {
        $value = $input->getParameterOption($option, null, true);

        if (! is_string($value) || $value === '' || str_starts_with($value, '-')) {
            return null;
        }

        return $value;
    }

    private static function isTty(): bool
    {
        if (! defined('STDIN') || ! defined('STDOUT')) {
            return false;
        }

        if (! function_exists('stream_isatty')) {
            return false;
        }

        return stream_isatty(STDIN) && stream_isatty(STDOUT);
    }
}
