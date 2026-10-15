<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit;

use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Bridge\Rector\PhpunitToTesto\ExpectExceptionToTestoRector;
use Testo\Bridge\Rector\Testing\Internal\RectorRunner;
use Testo\Codecov\CoversNothing;
use Testo\Core\Value\Status;
use Testo\Output\ConsoleStreams;
use Testo\Test;
use Tests\Bridge\Rector\Unit\Fixture\RecordingMessenger;

#[Test]
#[CoversNothing]
final class ExceptionExpectationRuntimeTest
{
    public function convertedExpectationsKeepTheirVerdictsAndEvaluationOrder(): void
    {
        $fixture = \dirname(__DIR__, 2) . '/src/PhpunitToTesto/ExpectExceptionToTestoRector/runtime_contracts.php.inc';
        [$input] = \explode("-----\n", \str_replace("\r\n", "\n", (string) \file_get_contents($fixture)), 2);
        $runner = new RectorRunner(new RecordingMessenger(), [ExpectExceptionToTestoRector::class]);
        $converted = $runner->convert($input);
        $file = \sys_get_temp_dir() . '/testo-converted-' . \bin2hex(\random_bytes(8)) . '.php';
        $sink = \fopen('php://memory', 'w+b');
        try {
            \file_put_contents($file, $converted);
            $app = Application::createFromConfig(new ApplicationConfig(
                suites: [new SuiteConfig('Converted', location: new FinderConfig(include: [$file]))],
            ));
            $app->getContainer()->set(new ConsoleStreams($sink, $sink), ConsoleStreams::class);
            $result = $app->run();
        } finally {
            \fclose($sink);
            @\unlink($file);
        }

        $totals = $result->summary;
        Assert::same([$totals->total(), $totals->metric('assertions'), $totals->count(Status::Passed), $totals->count(Status::Failed)], [12, 14, 9, 3]);
        $failures = [];
        $assertions = [];
        foreach ($result as $suite) {
            foreach ($suite as $case) {
                foreach ($case as $test) {
                    if ($test->status->isFailure()) {
                        $failures[] = $test->info->identity->fqn();
                    }
                    $assertions[$test->info->name] = $test->summary->metric('assertions');
                }
            }
        }
        Assert::same($failures, [
            'ExceptionRuntimeContracts::emptyMustFail',
            'ExceptionRuntimeContracts::dynamicEmptyMustFail',
            'ExceptionRuntimeContracts::independentPatternMustFail',
        ]);
        Assert::same($assertions['evaluationOrder'], 2);
        Assert::same($assertions['independentPattern'], 1);
        Assert::same($assertions['emptyMustFail'], 1);
    }
}
