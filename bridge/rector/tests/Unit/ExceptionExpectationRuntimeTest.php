<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit;

use Testo\Assert;
use Testo\Bridge\Rector\PhpunitToTesto\ExpectExceptionToTestoRector;
use Testo\Bridge\Rector\Testing\Internal\RectorRunner;
use Testo\Codecov\CoversNothing;
use Testo\Test;
use Tests\Bridge\Rector\Unit\Fixture\ConvertedRuntime;
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
        $result = ConvertedRuntime::run($converted);

        $totals = $result['report']['totals'];
        Assert::same([$totals['total'], $totals['assertions'], $totals['passed'], $totals['failed']], [12, 14, 9, 3]);
        Assert::same(\array_column($result['report']['failures'], 'test'), [
            'ExceptionRuntimeContracts::emptyMustFail',
            'ExceptionRuntimeContracts::dynamicEmptyMustFail',
            'ExceptionRuntimeContracts::independentPatternMustFail',
        ]);
        $junit = new \SimpleXMLElement($result['junit']);
        $assertions = [];
        foreach ($junit->xpath('//testcase') as $case) {
            $assertions[(string) $case['name']] = (int) $case['assertions'];
        }
        Assert::same($assertions['evaluationOrder'], 2);
        Assert::same($assertions['independentPattern'], 1);
        Assert::same($assertions['emptyMustFail'], 1);
    }
}
