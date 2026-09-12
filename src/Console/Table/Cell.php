<?php

declare(strict_types=1);

namespace Unolia\Cli\Console\Table;

use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * One value in a table with how it should look: dim for what matters less
 * (ids, versions), bold for what matters most, a tint, a link. Width is
 * always that of the plain text, so styling never breaks alignment.
 */
final class Cell
{
    private function __construct(
        public readonly string $text,
        public readonly bool $dim = false,
        public readonly bool $bold = false,
        public readonly ?string $color = null,
        public readonly ?string $url = null,
        public readonly ?string $plain = null,
    ) {}

    public static function text(mixed $value): self
    {
        return new self(is_scalar($value) ? (string) $value : '');
    }

    public static function empty(): self
    {
        return new self('');
    }

    public function dim(): self
    {
        return new self($this->text, true, $this->bold, $this->color, $this->url, $this->plain);
    }

    public function bold(): self
    {
        return new self($this->text, $this->dim, true, $this->color, $this->url, $this->plain);
    }

    /** A Symfony color name (green, yellow) or a hex value (#7aa7ff). Null leaves the cell as it is. */
    public function color(?string $color): self
    {
        return new self($this->text, $this->dim, $this->bold, $color ?? $this->color, $this->url, $this->plain);
    }

    public function link(?string $url): self
    {
        return new self($this->text, $this->dim, $this->bold, $this->color, $url !== '' ? $url : null, $this->plain);
    }

    /** What a pipe gets instead of the text: a word for a glyph, nothing for decoration. */
    public function plain(string $plain): self
    {
        return new self($this->text, $this->dim, $this->bold, $this->color, $this->url, $plain);
    }

    public function plainText(): string
    {
        return $this->plain ?? $this->text;
    }

    public function width(): int
    {
        return mb_strwidth($this->text);
    }

    /** The text with Symfony formatter tags and, when the terminal follows links, an OSC 8 wrapper. */
    public function styled(bool $links): string
    {
        $text = OutputFormatter::escape($this->text);

        if ($text === '') {
            return '';
        }

        $options = [];

        if ($this->color !== null) {
            $options[] = 'fg='.$this->color;
        } elseif ($this->dim) {
            $options[] = 'fg=gray';
        }

        if ($this->bold) {
            $options[] = 'options=bold';
        }

        if ($options !== []) {
            $text = '<'.implode(';', $options).'>'.$text.'</>';
        }

        if ($links && $this->url !== null) {
            // BEL ends the OSC 8 sequences. The other terminator, ESC backslash,
            // leaves a backslash right before the next "<", which Symfony's
            // formatter reads as an escaped tag and prints as text.
            $text = "\e]8;;".$this->url."\x07".$text."\e]8;;\x07";
        }

        return $text;
    }
}
