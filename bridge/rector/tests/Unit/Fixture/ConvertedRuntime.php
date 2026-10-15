<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit\Fixture;

/**
 * Executes converted PHP through the real CLI, including required failing controls.
 */
final class ConvertedRuntime
{
    /**
     * @return array{report: array, junit: string}
     */
    public static function run(string $source): array
    {
        $root = \dirname(__DIR__, 5);
        $directory = \sys_get_temp_dir() . '/testo-converted-' . \bin2hex(\random_bytes(8));
        \mkdir($directory);
        $input = $directory . '/converted.php';
        $config = $directory . '/testo.php';
        $junit = $directory . '/junit.xml';
        \file_put_contents($input, $source);
        \file_put_contents($config, '<?php require ' . \var_export($root . '/tests/bootstrap.php', true) . '; return new \\Testo\\Application\\Config\\ApplicationConfig(suites: [new \\Testo\\Application\\Config\\SuiteConfig("Converted", location: new \\Testo\\Application\\Config\\FinderConfig(include: [' . \var_export($input, true) . ']))]);');

        try {
            $process = \proc_open(
                [\PHP_BINARY, $root . '/vendor/bin/testo', '--config=' . $config, '--json', '--log-junit=' . $junit],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $root,
            );
            if (!\is_resource($process)) {
                throw new \RuntimeException('Unable to start the converted test suite');
            }
            \fclose($pipes[0]);
            $stdout = (string) \stream_get_contents($pipes[1]);
            $stderr = (string) \stream_get_contents($pipes[2]);
            \fclose($pipes[1]);
            \fclose($pipes[2]);
            $exit = \proc_close($process);
            $report = \json_decode($stdout, true);
            if (!\is_array($report) || !isset($report['status']) || !\is_file($junit)) {
                throw new \RuntimeException("Converted suite exited $exit without a report: $stdout\n$stderr");
            }
            return ['report' => $report, 'junit' => (string) \file_get_contents($junit)];
        } finally {
            foreach ([$input, $config, $junit] as $file) {
                \is_file($file) and \unlink($file);
            }
            \rmdir($directory);
        }
    }
}
