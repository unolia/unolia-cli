<?php

declare(strict_types=1);

namespace Tests\Support;

use LogicException;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Two buffers, so a test can assert on stdout and stderr separately.
 */
final class BufferedConsoleOutput extends BufferedOutput implements ConsoleOutputInterface
{
    private OutputInterface $stderr;

    public function __construct()
    {
        parent::__construct(self::VERBOSITY_NORMAL, false);

        $this->stderr = new BufferedOutput(self::VERBOSITY_NORMAL, false, $this->getFormatter());
    }

    public function getErrorOutput(): OutputInterface
    {
        return $this->stderr;
    }

    public function setErrorOutput(OutputInterface $error): void
    {
        $this->stderr = $error;
    }

    public function section(): ConsoleSectionOutput
    {
        throw new LogicException('Sections are not supported in tests.');
    }

    public function setDecorated(bool $decorated): void
    {
        parent::setDecorated($decorated);

        $this->stderr->setDecorated($decorated);
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        parent::setFormatter($formatter);

        $this->stderr->setFormatter($formatter);
    }

    public function setVerbosity(int $level): void
    {
        parent::setVerbosity($level);

        $this->stderr->setVerbosity($level);
    }

    public function errors(): string
    {
        return $this->stderr instanceof BufferedOutput ? $this->stderr->fetch() : '';
    }
}
