<?php

declare(strict_types=1);

namespace Unolia\Cli\Watch;

use Unolia\Cli\Support\Arr;

/**
 * One reading of whatever is being watched.
 */
final readonly class TargetState
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public array $data,
        public array $meta = [],
        public array $extra = [],
    ) {}

    public function get(string $path, mixed $default = null): mixed
    {
        return Arr::get($this->data, $path, $default);
    }

    public function string(string $path, ?string $default = null): ?string
    {
        $value = $this->get($path);

        return is_string($value) ? $value : $default;
    }

    public function int(string $path, ?int $default = null): ?int
    {
        $value = $this->get($path);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return ['data' => $this->data, 'meta' => $this->meta];
    }
}
