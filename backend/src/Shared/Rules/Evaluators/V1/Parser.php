<?php

declare(strict_types=1);

namespace Fundly\Shared\Rules\Evaluators\V1;

use Fundly\Shared\Rules\RuleError;

/**
 * Pratt parser producing a plain-array AST (serialisable, so a parsed rule
 * can be inspected and validated without executing it).
 *
 * Node shapes: ['num', '1.25'] ['str', 'x'] ['bool', true] ['null']
 * ['var', 'applicant.age'] ['list', [...]] ['unary', op, node]
 * ['binary', op, l, r] ['cond', test, then, else] ['call', name, [args]]
 */
final class Parser
{
    private const PRECEDENCE = [
        'or' => 10, 'and' => 20,
        '==' => 30, '!=' => 30, '<' => 30, '<=' => 30, '>' => 30, '>=' => 30, 'in' => 30, 'not in' => 30,
        '+' => 40, '-' => 40, '*' => 50, '/' => 50, '%' => 50,
    ];

    /** @var list<Token> */
    private array $tokens;

    private int $p = 0;

    private function __construct(private readonly string $src)
    {
        $this->tokens = Lexer::tokenize($src);
    }

    /** @return array<int, mixed> */
    public static function parse(string $src): array
    {
        if (trim($src) === '') {
            throw new RuleError('Empty expression.');
        }
        $parser = new self($src);
        $node = $parser->expression(0);
        if (! $parser->peek()->is(Token::END)) {
            throw new RuleError("Unexpected '{$parser->peek()->value}' at position {$parser->peek()->pos} in: {$src}");
        }

        return $node;
    }

    /** @return array<int, mixed> */
    private function expression(int $minPrec): array
    {
        $left = $this->prefix();
        while (true) {
            $t = $this->peek();
            if ($t->is(Token::OP, '?') && $minPrec === 0) {
                $this->p++;
                $then = $this->expression(0);
                $this->expect(Token::OP, ':');
                $else = $this->expression(0);
                $left = ['cond', $left, $then, $else];

                continue;
            }
            $prec = $t->type === Token::OP ? (self::PRECEDENCE[$t->value] ?? null) : null;
            if ($prec === null || $prec <= $minPrec) {
                return $left;
            }
            $this->p++;
            $right = $this->expression($prec);
            $left = ['binary', $t->value, $left, $right];
        }
    }

    /** @return array<int, mixed> */
    private function prefix(): array
    {
        $t = $this->next();
        switch (true) {
            case $t->is(Token::NUMBER):
                return ['num', $t->value];
            case $t->is(Token::STRING):
                return ['str', $t->value];
            case $t->is(Token::OP, '-'):
                return ['unary', '-', $this->expression(45)];
            case $t->is(Token::OP, 'not'):
                return ['unary', 'not', $this->expression(25)];
            case $t->is(Token::PUNCT, '('):
                $e = $this->expression(0);
                $this->expect(Token::PUNCT, ')');

                return $e;
            case $t->is(Token::PUNCT, '['):
                $items = [];
                if (! $this->peek()->is(Token::PUNCT, ']')) {
                    do {
                        $items[] = $this->expression(0);
                    } while ($this->accept(Token::PUNCT, ','));
                }
                $this->expect(Token::PUNCT, ']');

                return ['list', $items];
            case $t->is(Token::NAME):
                return match ($t->value) {
                    'true' => ['bool', true],
                    'false' => ['bool', false],
                    'null' => ['null'],
                    default => $this->peek()->is(Token::PUNCT, '(') ? $this->call($t->value) : ['var', $t->value],
                };
        }
        throw new RuleError("Unexpected '{$t->value}' at position {$t->pos} in: {$this->src}");
    }

    /** @return array<int, mixed> */
    private function call(string $name): array
    {
        $this->expect(Token::PUNCT, '(');
        $args = [];
        if (! $this->peek()->is(Token::PUNCT, ')')) {
            do {
                $args[] = $this->expression(0);
            } while ($this->accept(Token::PUNCT, ','));
        }
        $this->expect(Token::PUNCT, ')');

        return ['call', $name, $args];
    }

    private function peek(): Token
    {
        return $this->tokens[$this->p];
    }

    private function next(): Token
    {
        return $this->tokens[$this->p++] ?? $this->tokens[count($this->tokens) - 1];
    }

    private function accept(string $type, string $value): bool
    {
        if ($this->peek()->is($type, $value)) {
            $this->p++;

            return true;
        }

        return false;
    }

    private function expect(string $type, string $value): void
    {
        if (! $this->accept($type, $value)) {
            throw new RuleError("Expected '{$value}' at position {$this->peek()->pos} in: {$this->src}");
        }
    }
}
