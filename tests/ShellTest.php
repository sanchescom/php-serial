<?php

declare(strict_types=1);

namespace Sanchescom\Serial\Test;

use PHPUnit\Framework\TestCase;
use Sanchescom\Serial\Shell;

final class ShellTest extends TestCase
{
    public function testRunReportsTheCommandOutputAndExitCode(): void
    {
        self::assertSame([0, ['hi']], Shell::run('echo hi'));
    }

    public function testDisabledExecIsReportedInsteadOfFatal(): void
    {
        $script = 'require ' . var_export(dirname(__DIR__) . '/vendor/autoload.php', true)
            . '; [$code, $output] = ' . Shell::class . '::run("echo hi"); echo $code, "|", $output[0];';
        $result = shell_exec(sprintf(
            '%s -d disable_functions=exec -r %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script),
        ));

        self::assertSame('1|exec() is disabled in this PHP installation', trim((string) $result));
    }
}
