<?php

namespace Ahamed\FrameworkMcp\Tests\Support;

/**
 * Collects log lines in memory instead of writing the framework log file.
 */
class RecordingLog
{
    public array $lines = [];

    public function __call($level, $arguments)
    {
        $this->lines[] = [$level, (string) ($arguments[0] ?? '')];
    }

    public function contains(string $needle, string $level = 'error'): bool
    {
        foreach ($this->lines as [$line_level, $message]) {
            if ($line_level === $level && strpos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
