<?php

namespace Prisma\Sif\Tests\Support;

final class ScriptRunner
{
    /** Execute a real CLI entry point without a shell, inheriting test credentials. */
    public static function run(string $script, array $environment = [], array $arguments = []): array
    {
        $stdout = tmpfile();
        $stderr = tmpfile();
        if ($stdout === false || $stderr === false) {
            throw new \RuntimeException('Cannot create CLI output files.');
        }
        try {
            $process = proc_open(
                array_merge([PHP_BINARY, dirname(__DIR__, 2) . '/' . $script], $arguments),
                [0 => ['pipe', 'r'], 1 => $stdout, 2 => $stderr],
                $pipes,
                dirname(__DIR__, 3),
                array_replace(getenv(), $environment),
                ['bypass_shell' => true]
            );
            if (!is_resource($process)) {
                throw new \RuntimeException('Cannot launch PHP CLI.');
            }
            fclose($pipes[0]);
            $deadline = microtime(true) + 30;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (microtime(true) >= $deadline) {
                    proc_terminate($process);
                    proc_close($process);
                    throw new \RuntimeException('PHP CLI test timed out.');
                }
                usleep(10000);
            } while (true);
            $exitCode = $status['exitcode'];
            proc_close($process);
            rewind($stdout);
            rewind($stderr);
            return [
                'exit_code' => $exitCode,
                'stdout' => stream_get_contents($stdout),
                'stderr' => stream_get_contents($stderr),
            ];
        } finally {
            fclose($stdout);
            fclose($stderr);
        }
    }
}
