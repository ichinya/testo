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
        \Testo\Expect::exception(\RuntimeException::class)->withMessageMatchingRegex('~one~')->withMessageMatchingRegex('~two~');
        throw new \RuntimeException('two');
    }

    public function namedArguments(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining('named')->withCode(7);
        throw new \RuntimeException('named', 7);
    }

    public function literalRegexCharacters(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageMatchingRegex('~old~')->withMessageMatchingRegex('~\~\.\[x\]\+\$\\\\~');
        throw new \RuntimeException('prefix ~.[x]+$\\ suffix');
    }

    public function dynamicEmptyMustFail(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageMatchingRegex((static fn(string $message): string => $message === '' ? '~\A\z~' : '~' . \preg_quote($message, '~') . '~')($this->mark('')));
        throw new \RuntimeException('nonempty');
    }

    public function evaluationOrder(): void
    {
        $this->trace = [];
        \Testo\Expect::exception($this->mark(\RuntimeException::class))->withMessageMatchingRegex((static fn(string $message): string => $message === '' ? '~\A\z~' : '~' . \preg_quote($message, '~') . '~')($this->mark('one')))->withMessageMatchingRegex((static fn(string $message): string => $message === '' ? '~\A\z~' : '~' . \preg_quote($message, '~') . '~')($this->mark('two')))->withCode(1)->withCode(2);
        \Testo\Assert::same($this->trace, [\RuntimeException::class, 'one', 'two']);
        throw new \RuntimeException('two', 2);
    }

    public function overwrittenArgumentThrows(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageMatchingRegex('~one~')->withMessageMatchingRegex((static fn(string $message): string => $message === '' ? '~\A\z~' : '~' . \preg_quote($message, '~') . '~')($this->throwOne()));
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
        $testoExpectedException->withMessageMatchingRegex((static fn(string $message): string => $message === '' ? '~\A\z~' : '~' . \preg_quote($message, '~') . '~')($message));
        $class = \LogicException::class;
        $message = 'two';
        $testoExpectedException->withMessageMatchingRegex((static fn(string $message): string => $message === '' ? '~\A\z~' : '~' . \preg_quote($message, '~') . '~')($message));
        throw new \RuntimeException('two');
    }

    public function temporaryDoesNotClobberSource(): void
    {
        $testoExpectedException = 'source';
        $testoExpectedException2 = \Testo\Expect::exception(\RuntimeException::class);
        $message = 'two';
        $testoExpectedException2->withMessageMatchingRegex((static fn(string $message): string => $message === '' ? '~\A\z~' : '~' . \preg_quote($message, '~') . '~')($message));
        \Testo\Assert::same($testoExpectedException, 'source');
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
