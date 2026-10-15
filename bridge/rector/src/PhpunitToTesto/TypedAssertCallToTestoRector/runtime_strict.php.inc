<?php

declare(strict_types=1);

final class StrictStringRuntimeContracts extends \PHPUnit\Framework\TestCase
{
    public function testValidNamed(): void
    {
        $this->assertStringContainsString(haystack: 'xyz', needle: 'x');
    }

    public function testIntegerMustError(): void
    {
        $this->assertStringContainsString('2', 123);
    }

    public function testStringableMustError(): void
    {
        $this->assertStringContainsString('x', new class implements \Stringable {
            public function __toString(): string { return 'xyz'; }
        });
    }

    public function testInvalidMessageMustError(): void
    {
        $this->assertStringContainsString('x', 'xyz', message: 123);
    }
}
-----
<?php

declare(strict_types=1);

final class StrictStringRuntimeContracts extends \PHPUnit\Framework\TestCase
{
    public function testValidNamed(): void
    {
        \Testo\Assert::string('xyz')->contains('x');
    }

    public function testIntegerMustError(): void
    {
        (static function (string $needle, string $haystack, string $message = ''): void {
            \Testo\Assert::string($haystack)->contains($needle, $message);
        })('2', 123);
    }

    public function testStringableMustError(): void
    {
        (static function (string $needle, string $haystack, string $message = ''): void {
            \Testo\Assert::string($haystack)->contains($needle, $message);
        })('x', new class implements \Stringable {
            public function __toString(): string { return 'xyz'; }
        });
    }

    public function testInvalidMessageMustError(): void
    {
        (static function (string $needle, string $haystack, string $message = ''): void {
            \Testo\Assert::string($haystack)->contains($needle, $message);
        })('x', 'xyz', message: 123);
    }
}
