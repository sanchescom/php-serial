<?php

declare(strict_types=1);

namespace Sanchescom\Serial;

/** @internal Runs one command and returns its exit code and output lines. */
final class Shell
{
    /** @return array{int, list<string>} */
    public static function run(string $command): array
    {
        if (!function_exists('exec')) {
            return [1, ['exec() is disabled in this PHP installation']];
        }

        exec($command . ' 2>&1', $output, $exitCode);

        return [$exitCode, $output];
    }
}
