<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use Rector\Contract\PhpParser\Node\StmtsAwareInterface;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PhpParser\Enum\NodeGroup;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Folds a run of PHPUnit `$this->expect*` exception statements into a single fluent
 * `\Testo\Expect::exception(...)` chain.
 *
 * The head `$this->expectException($c)` (also `self::`/`static::`) becomes
 * `\Testo\Expect::exception($c)`, and each immediately-following sibling statement is absorbed as a
 * chained modifier and removed:
 *   - a single nonempty literal message => `->withMessageContaining($m)`;
 *   - an empty literal message => `->withMessage('')`;
 *   - repeated or dynamic messages => a quoted, replaceable `->withMessageMatchingRegex()`;
 *   - `expectExceptionMessageMatches($re)` => `->withMessageMatchingRegex($re)`;
 *   - `expectExceptionCode($n)` => `->withCode($n)`.
 *
 *     $this->expectException(\RuntimeException::class);
 *     $this->expectExceptionMessage('boom');
 *     $this->expectExceptionCode(7);
 *     // becomes
 *     \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining('boom')->withCode(7);
 *
 * Because this needs cross-statement reasoning (which calls belong together, ordering, intervening
 * statements), it operates at the statements level: it matches the enclosing
 * {@see StmtsAwareInterface} node and rewrites its `->stmts`.
 *
 * Arguments keep their evaluation order, including overwritten expectations. A typed closure
 * converts a dynamic message to a literal substring pattern (exact-empty for an empty value).
 * Named source arguments are validated and emitted positionally. Across assignments, a fresh local
 * holds the handler so registration and argument snapshots stay at their original statements.
 * Any other statement ends the run. A bare message/code with no preceding class is left untouched.
 *
 * PHPUnit has independent message and regex fields. A single literal message can coexist with
 * a regex in Testo; a dynamic/repeated message plus an independent regex cannot share Testo's one
 * replaceable regex field and the whole run is left for manual migration.
 *
 * Only folds inside a class: the expectations belong to a test method, so a run in a free function
 * or at namespace level is left untouched.
 */
#[TestRectorFixtures('ExpectExceptionToTestoRector')]
final class ExpectExceptionToTestoRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit `$this->expectException()` (with consecutive expectExceptionMessage/Code) into a fluent `\Testo\Expect::exception()` chain',
            [
                new CodeSample(
                    <<<'PHP'
                        $this->expectException(\RuntimeException::class);
                        $this->expectExceptionMessage('boom');
                        $this->expectExceptionCode(7);
                        PHP,
                    <<<'PHP'
                        \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining('boom')->withCode(7);
                        PHP,
                ),
            ],
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    #[\Override]
    public function getNodeTypes(): array
    {
        return NodeGroup::STMTS_AWARE;
    }

    /**
     * @param StmtsAwareInterface $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $stmts = $node->stmts;
        if ($stmts === null) {
            return null;
        }

        # Only fold inside a class: exception expectations belong to a test method. A run of
        # `$this->expect*` in a free function or at namespace level is left untouched.
        if (!$this->isInClassScope($node)) {
            return null;
        }

        $changed = false;
        $result = [];
        $count = \count($stmts);

        for ($i = 0; $i < $count; ++$i) {
            $stmt = $stmts[$i];

            $head = $stmt instanceof Expression ? $this->matchExpectException($stmt) : null;
            if ($head === null) {
                $result[] = $stmt;
                continue;
            }

            $argument = $this->argument($head, 'exception');
            if ($argument === null) {
                $result[] = $stmt;
                continue;
            }

            $chain = new StaticCall(
                new FullyQualified('Testo\\Expect'),
                new Identifier('exception'),
                [new Arg($argument)],
            );

            # Collect sibling modifiers, retaining intervening assignments in their source order.
            $modifiers = [];
            $pending = [];
            $hasAssignments = false;
            $hasMessage = false;
            $hasPattern = false;
            $messageCount = 0;
            $literalMessage = false;
            for ($j = $i + 1; $j < $count; ++$j) {
                $next = $stmts[$j];
                $modifier = $next instanceof Expression ? $this->matchModifier($next) : null;
                if ($modifier !== null) {
                    $hasAssignments = $hasAssignments || $pending !== [];
                    \array_push($modifiers, ...$pending);
                    $modifiers[] = [$next, $modifier];
                    $hasMessage = $hasMessage || $modifier[0] === 'message';
                    $hasPattern = $hasPattern || $modifier[0] === 'withMessageMatchingRegex';
                    $messageCount += (int) ($modifier[0] === 'message');
                    $literalMessage = $literalMessage || ($modifier[0] === 'message' && $modifier[1] instanceof String_);
                    $pending = [];
                    $i = $j;
                    continue;
                }

                if (!$this->isSafeAssignment($next)) {
                    break;
                }

                $pending[] = $next;
            }

            \assert($stmt instanceof Expression);
            if ($hasMessage && $hasPattern && ($messageCount !== 1 || !$literalMessage)) {
                # There is only one replaceable regex slot in Testo. Do not conflate PHPUnit's
                # independent fields, or register a second handler that could consume a failure
                # from the first when the expected class is Throwable/Exception.
                $result[] = $stmt;
                foreach ($modifiers as $modifier) {
                    $result[] = \is_array($modifier) ? $modifier[0] : $modifier;
                }
                continue;
            }
            if ($hasAssignments) {
                # Keep registration and every argument evaluation at its original statement.
                $expectation = $this->temporaryName($node);
                if ($expectation === null) {
                    $result[] = $stmt;
                    foreach ($modifiers as $modifier) {
                        $result[] = \is_array($modifier) ? $modifier[0] : $modifier;
                    }
                    continue;
                }

                $stmt->expr = new Assign($expectation, $chain);
                $result[] = $stmt;
                foreach ($modifiers as $modifier) {
                    if (!\is_array($modifier)) {
                        $result[] = $modifier;
                        continue;
                    }
                    [$statement, [$method, $value]] = $modifier;
                    $statement->expr = $this->modifier($expectation, $method, $value, $messageCount);
                    $result[] = $statement;
                }
                $changed = true;
                continue;
            }

            foreach ($modifiers as [, [$method, $value]]) {
                $chain = $this->modifier($chain, $method, $value, $messageCount);
            }
            $stmt->expr = $chain;
            $result[] = $stmt;
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        $node->stmts = $result;

        return $node;
    }

    /**
     * Returns the `expectException` call expression if the statement is a bare
     * `$this->expectException(...)` (or `self::`/`static::`), otherwise null.
     */
    private function matchExpectException(Expression $stmt): MethodCall|StaticCall|null
    {
        $expr = $stmt->expr;

        if ($expr instanceof MethodCall) {
            if (!$this->isName($expr->var, 'this')) {
                return null;
            }
        } elseif ($expr instanceof StaticCall) {
            if (!$this->isName($expr->class, 'self') && !$this->isName($expr->class, 'static')) {
                return null;
            }
        } else {
            return null;
        }

        return $this->isName($expr->name, 'expectException') ? $expr : null;
    }

    /**
     * Maps a foldable `$this->expectExceptionMessage/MessageMatches/Code(...)` statement to a `[method, args]`
     * pair for the fluent chain, or null when the statement is not a foldable modifier.
     *
     * @return array{non-empty-string, Expr}|null
     */
    private function matchModifier(Expression $stmt): ?array
    {
        $expr = $stmt->expr;

        if ($expr instanceof MethodCall) {
            if (!$this->isName($expr->var, 'this')) {
                return null;
            }
        } elseif ($expr instanceof StaticCall) {
            if (!$this->isName($expr->class, 'self') && !$this->isName($expr->class, 'static')) {
                return null;
            }
        } else {
            return null;
        }

        $mapping = match (true) {
            $this->isName($expr->name, 'expectExceptionMessage') => ['message', 'message'],
            $this->isName($expr->name, 'expectExceptionMessageMatches') => ['withMessageMatchingRegex', 'regularExpression'],
            $this->isName($expr->name, 'expectExceptionCode') => ['withCode', 'code'],
            default => null,
        };
        if ($mapping === null) {
            return null;
        }
        $argument = $this->argument($expr, $mapping[1]);
        return $argument === null ? null : [$mapping[0], $argument];
    }

    private function argument(MethodCall|StaticCall $call, string $parameter): ?Expr
    {
        if (\count($call->args) !== 1 || !$call->args[0] instanceof Arg) {
            return null;
        }
        $argument = $call->args[0];
        return $argument->unpack || ($argument->name !== null && $argument->name->toString() !== $parameter)
            ? null : $argument->value;
    }

    private function modifier(Expr $receiver, string $method, Expr $value, int $messageCount): MethodCall
    {
        if ($method === 'message') {
            if ($messageCount === 1 && $value instanceof String_) {
                $method = $value->value === '' ? 'withMessage' : 'withMessageContaining';
            } else {
                $method = 'withMessageMatchingRegex';
                $value = $this->messagePattern($value);
            }
        }
        return new MethodCall($receiver, new Identifier($method), [new Arg($value)]);
    }

    private function messagePattern(Expr $message): Expr
    {
        if ($message instanceof String_) {
            return new String_($message->value === '' ? '~\\A\\z~' : '~' . \preg_quote($message->value, '~') . '~');
        }

        # A typed closure keeps PHPUnit's string parameter boundary (weak/strict caller coercion)
        # and evaluates the original expression only once, including an overwritten expectation.
        $value = new Variable('message');
        return new FuncCall(new ArrowFunction([
            'static' => true,
            'params' => [new Param($value, type: new Identifier('string'))],
            'returnType' => new Identifier('string'),
            'expr' => new Ternary(
                new Identical($value, new String_('')),
                new String_('~\\A\\z~'),
                new Concat(new Concat(new String_('~'), new FuncCall(new FullyQualified('preg_quote'), [
                    new Arg($value), new Arg(new String_('~')),
                ])), new String_('~')),
            ),
        ]), [new Arg($message)]);
    }

    private function temporaryName(Node $node): ?Variable
    {
        $used = [];
        foreach ((new NodeFinder())->findInstanceOf($node, Variable::class) as $variable) {
            if (!\is_string($variable->name)) {
                return null;
            }
            $used[$variable->name] = true;
        }
        $scope = $node->getAttribute(AttributeKey::SCOPE);
        $name = 'testoExpectedException';
        for ($suffix = 2; isset($used[$name]) || ($scope instanceof Scope && $scope->hasVariableType($name)->yes()); ++$suffix) {
            $name = 'testoExpectedException' . $suffix;
        }
        return new Variable($name);
    }

    /**
     * Whether the statement assigns a value that cannot throw to a plain variable: a scalar, a
     * constant, or a concatenation or array built from them and from variables.
     */
    private function isSafeAssignment(Node $stmt): bool
    {
        return $stmt instanceof Expression
            && $stmt->expr instanceof Assign
            && $stmt->expr->var instanceof Variable
            && $this->isSafeValue($stmt->expr->expr);
    }

    private function isSafeValue(Expr $expr): bool
    {
        return match (true) {
            $expr instanceof Scalar, $expr instanceof ConstFetch, $expr instanceof Variable => true,
            $expr instanceof ClassConstFetch => $expr->class instanceof Name && $expr->name instanceof Identifier,
            $expr instanceof Concat => $this->isSafeValue($expr->left) && $this->isSafeValue($expr->right),
            $expr instanceof Array_ => $this->isSafeArray($expr),
            default => false,
        };
    }

    private function isSafeArray(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if ($item->unpack
                || ($item->key !== null && !$this->isSafeValue($item->key))
                || !$this->isSafeValue($item->value)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the statements node sits inside a class. Exception expectations are only folded there;
     * a run in a free function or at namespace level is left untouched.
     */
    private function isInClassScope(Node $node): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->isInClass();
    }
}
