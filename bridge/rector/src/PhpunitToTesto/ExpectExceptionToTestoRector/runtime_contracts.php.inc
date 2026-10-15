<?php

#[\Testo\Test]
final class ExceptionRuntimeContracts
{
    private array $trace = [];

    public function emptyMustFail(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('');
        throw new \RuntimeException('nonempty');
    }

    public function emptyPasses(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('');
        throw new \RuntimeException('');
    }

    public function lastMessageWins(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('one');
        $this->expectExceptionMessage('two');
        throw new \RuntimeException('two');
    }

    public function namedArguments(): void
    {
        $this->expectException(exception: \RuntimeException::class);
        $this->expectExceptionMessage(message: 'named');
        $this->expectExceptionCode(code: 7);
        throw new \RuntimeException('named', 7);
    }

    public function literalRegexCharacters(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('old');
        $this->expectExceptionMessage('~.[x]+$\\');
        throw new \RuntimeException('prefix ~.[x]+$\\ suffix');
    }

    public function dynamicEmptyMustFail(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($this->mark(''));
        throw new \RuntimeException('nonempty');
    }

    public function evaluationOrder(): void
    {
        $this->trace = [];
        $this->expectException($this->mark(\RuntimeException::class));
        $this->expectExceptionMessage($this->mark('one'));
        $this->expectExceptionMessage($this->mark('two'));
        $this->expectExceptionCode(1);
        $this->expectExceptionCode(2);
        \Testo\Assert::same($this->trace, [\RuntimeException::class, 'one', 'two']);
        throw new \RuntimeException('two', 2);
    }

    public function overwrittenArgumentThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('one');
        $this->expectExceptionMessage($this->throwOne());
    }

    public function independentPattern(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('two');
        $this->expectExceptionMessageMatches(regularExpression: '~^two$~');
        throw new \RuntimeException('two');
    }

    public function independentPatternMustFail(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('two');
        $this->expectExceptionMessageMatches('~^three$~');
        throw new \RuntimeException('two');
    }

    public function assignmentKeepsSnapshots(): void
    {
        $class = \RuntimeException::class;
        $message = 'one';
        $this->expectException($class);
        $this->expectExceptionMessage($message);
        $class = \LogicException::class;
        $message = 'two';
        $this->expectExceptionMessage($message);
        throw new \RuntimeException('two');
    }

    public function temporaryDoesNotClobberSource(): void
    {
        $testoExpectedException = 'source';
        $this->expectException(\RuntimeException::class);
        $message = 'two';
        $this->expectExceptionMessage($message);
        \Testo\Assert::same($testoExpectedException, 'source');
        throw new \RuntimeException('two');
    }

    public function longDynamicMessage(): void
    {
        $message = str_repeat('a', 100000);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);
        throw new \RuntimeException('prefix ' . $message . ' suffix');
    }

    public function longRepeatedMessage(): void
    {
        $message = str_repeat('a', 100000);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('old');
        $this->expectExceptionMessage($message);
        throw new \RuntimeException('prefix ' . $message . ' suffix');
    }

    public function longMessageMustFail(): void
    {
        $message = str_repeat('a', 100000);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);
        throw new \RuntimeException(str_repeat('a', 99999));
    }

    public function lastEmptyWins(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('old');
        $this->expectExceptionMessage('');
        throw new \RuntimeException('');
    }

    public function lastNonemptyWins(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('');
        $this->expectExceptionMessage('new');
        throw new \RuntimeException('prefix new suffix');
    }

    public function dynamicMessageAndPattern(): void
    {
        $message = 'two';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);
        $this->expectExceptionMessageMatches('~^two$~');
        throw new \RuntimeException('two');
    }

    public function repeatedMessageAndPatternMustFail(): void
    {
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('old');
        $this->expectExceptionMessageMatches('~^three$~');
        $this->expectExceptionMessage($this->mark('two'));
        throw new \RuntimeException('two');
    }

    private function mark(string $value): string
    {
        $this->trace[] = $value;
        return $value;
    }

    private function throwOne(): string
    {
        throw new \RuntimeException('one');
    }
}
-----
<?php

#[\Testo\Test]
final class ExceptionRuntimeContracts
{
    private array $trace = [];

    public function emptyMustFail(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('');
        throw new \RuntimeException('nonempty');
    }

    public function emptyPasses(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('');
        throw new \RuntimeException('');
    }

    public function lastMessageWins(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('one', exact: false)->withMessage('two', exact: false);
        throw new \RuntimeException('two');
    }

    public function namedArguments(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining('named')->withCode(7);
        throw new \RuntimeException('named', 7);
    }

    public function literalRegexCharacters(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('old', exact: false)->withMessage('~.[x]+$\\', exact: false);
        throw new \RuntimeException('prefix ~.[x]+$\\ suffix');
    }

    public function dynamicEmptyMustFail(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage(...(static fn(string $message): array => [$message, $message === ''])($this->mark('')));
        throw new \RuntimeException('nonempty');
    }

    public function evaluationOrder(): void
    {
        $this->trace = [];
        \Testo\Expect::exception($this->mark(\RuntimeException::class))->withMessage(...(static fn(string $message): array => [$message, $message === ''])($this->mark('one')))->withMessage(...(static fn(string $message): array => [$message, $message === ''])($this->mark('two')))->withCode(1)->withCode(2);
        \Testo\Assert::same($this->trace, [\RuntimeException::class, 'one', 'two']);
        throw new \RuntimeException('two', 2);
    }

    public function overwrittenArgumentThrows(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('one', exact: false)->withMessage(...(static fn(string $message): array => [$message, $message === ''])($this->throwOne()));
    }

    public function independentPattern(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining('two')->withMessageMatchingRegex('~^two$~');
        throw new \RuntimeException('two');
    }

    public function independentPatternMustFail(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining('two')->withMessageMatchingRegex('~^three$~');
        throw new \RuntimeException('two');
    }

    public function assignmentKeepsSnapshots(): void
    {
        $class = \RuntimeException::class;
        $message = 'one';
        $testoExpectedException = \Testo\Expect::exception($class);
        $testoExpectedException->withMessage(...(static fn(string $message): array => [$message, $message === ''])($message));
        $class = \LogicException::class;
        $message = 'two';
        $testoExpectedException->withMessage(...(static fn(string $message): array => [$message, $message === ''])($message));
        throw new \RuntimeException('two');
    }

    public function temporaryDoesNotClobberSource(): void
    {
        $testoExpectedException = 'source';
        $testoExpectedException2 = \Testo\Expect::exception(\RuntimeException::class);
        $message = 'two';
        $testoExpectedException2->withMessage(...(static fn(string $message): array => [$message, $message === ''])($message));
        \Testo\Assert::same($testoExpectedException, 'source');
        throw new \RuntimeException('two');
    }

    public function longDynamicMessage(): void
    {
        $message = str_repeat('a', 100000);
        \Testo\Expect::exception(\RuntimeException::class)->withMessage(...(static fn(string $message): array => [$message, $message === ''])($message));
        throw new \RuntimeException('prefix ' . $message . ' suffix');
    }

    public function longRepeatedMessage(): void
    {
        $message = str_repeat('a', 100000);
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('old', exact: false)->withMessage(...(static fn(string $message): array => [$message, $message === ''])($message));
        throw new \RuntimeException('prefix ' . $message . ' suffix');
    }

    public function longMessageMustFail(): void
    {
        $message = str_repeat('a', 100000);
        \Testo\Expect::exception(\RuntimeException::class)->withMessage(...(static fn(string $message): array => [$message, $message === ''])($message));
        throw new \RuntimeException(str_repeat('a', 99999));
    }

    public function lastEmptyWins(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('old', exact: false)->withMessage('', exact: true);
        throw new \RuntimeException('');
    }

    public function lastNonemptyWins(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('', exact: true)->withMessage('new', exact: false);
        throw new \RuntimeException('prefix new suffix');
    }

    public function dynamicMessageAndPattern(): void
    {
        $message = 'two';
        \Testo\Expect::exception(\RuntimeException::class)->withMessage(...(static fn(string $message): array => [$message, $message === ''])($message))->withMessageMatchingRegex('~^two$~');
        throw new \RuntimeException('two');
    }

    public function repeatedMessageAndPatternMustFail(): void
    {
        \Testo\Expect::exception(\Throwable::class)->withMessage('old', exact: false)->withMessageMatchingRegex('~^three$~')->withMessage(...(static fn(string $message): array => [$message, $message === ''])($this->mark('two')));
        throw new \RuntimeException('two');
    }

    private function mark(string $value): string
    {
        $this->trace[] = $value;
        return $value;
    }

    private function throwOne(): string
    {
        throw new \RuntimeException('one');
    }
}
