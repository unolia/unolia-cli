<?php

declare(strict_types=1);

namespace Unolia\Cli\Console;

use RuntimeException;

/**
 * Every failure a user can cause or hit. The renderer turns it into one stderr line
 * or one JSON envelope, and its exit code becomes the process exit code.
 */
final class CliError extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ExitCode $exitCode,
        public readonly ?string $hint = null,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function usage(string $message, ?string $hint = null, array $details = []): self
    {
        return new self('usage', $message, ExitCode::Usage, $hint, $details);
    }

    /**
     * @param  array<int|string, string>  $candidates
     */
    public static function missingInput(string $flag, array $candidates = []): self
    {
        $message = sprintf('missing %s', $flag);

        if ($candidates !== []) {
            $message .= '. Candidates: '.implode(', ', self::describeCandidates($candidates));
        }

        return new self(
            'missing_input',
            $message,
            ExitCode::Usage,
            sprintf('Pass %s, or run this command in a terminal.', $flag),
            ['flag' => $flag, 'candidates' => array_values($candidates)],
        );
    }

    public static function confirmationRequired(string $question): self
    {
        return new self(
            'confirmation_required',
            sprintf('this command needs a confirmation: %s', $question),
            ExitCode::Usage,
            'Pass --yes to confirm, or --dry-run to see the plan.',
            ['question' => $question],
        );
    }

    /**
     * @param  array<int|string, string>  $candidates
     */
    public static function unlinkedDirectory(array $candidates = [], ?string $remote = null): self
    {
        $message = 'this directory is not linked to a website';

        if ($remote !== null) {
            $message .= sprintf(' (git remote %s)', $remote);
        }

        if ($candidates !== []) {
            $message .= '. Candidates: '.implode(', ', self::describeCandidates($candidates));
        }

        return new self(
            'unlinked_directory',
            $message,
            ExitCode::Usage,
            'Run unolia init, or pass --website <id>.',
            ['remote' => $remote, 'candidates' => array_values($candidates)],
        );
    }

    public static function auth(string $message, ?string $hint = 'Run unolia login'): self
    {
        return new self('unauthenticated', $message, ExitCode::Auth, $hint);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function notFound(string $message, ?string $hint = null, array $details = []): self
    {
        return new self('not_found', $message, ExitCode::NotFound, $hint, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function forbidden(string $message, array $details = [], ?string $hint = null): self
    {
        return new self('forbidden', $message, ExitCode::Forbidden, $hint, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function timeout(string $message, ?string $hint = null, array $details = []): self
    {
        return new self('timeout', $message, ExitCode::Timeout, $hint, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function remoteFailure(string $message, ?string $hint = null, array $details = []): self
    {
        return new self('remote_failure', $message, ExitCode::RemoteFailure, $hint, $details);
    }

    public static function interrupted(string $message): self
    {
        return new self('interrupted', $message, ExitCode::Interrupted);
    }

    /**
     * @return array{code: string, message: string, hint: string|null, exit_code: int, details: array<string, mixed>}
     */
    public function envelope(): array
    {
        return [
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'hint' => $this->hint,
            'exit_code' => $this->exitCode->value,
            'details' => $this->details,
        ];
    }

    /**
     * @param  array<int|string, string>  $candidates
     * @return list<string>
     */
    private static function describeCandidates(array $candidates): array
    {
        $described = [];

        foreach ($candidates as $key => $label) {
            $described[] = is_int($key) ? $label : $key.' '.$label;
        }

        return array_slice($described, 0, 10);
    }
}
