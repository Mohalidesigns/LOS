<?php

declare(strict_types=1);

namespace Fundly\Shared\Rules\Evaluators\V1;

use Fundly\Shared\Rules\RuleError;

/**
 * Tokeniser for the V1 rule language. Numbers stay as their source text so
 * they are parsed as exact decimals (no float ever exists).
 */
final class Lexer
{
    private const OPERATORS = ['not in', '==', '!=', '<=', '>=', '&&', '||', '<', '>', '+', '-', '*', '/', '%', '!', '?', ':'];

    private const WORD_OPERATORS = ['and', 'or', 'not', 'in'];

    /** @return list<Token> */
    public static function tokenize(string $src): array
    {
        $tokens = [];
        $i = 0;
        $n = strlen($src);
        while ($i < $n) {
            $c = $src[$i];
            if (ctype_space($c)) {
                $i++;

                continue;
            }
            if (ctype_digit($c)) {
                if (preg_match('/\G\d+(\.\d+)?/', $src, $m, 0, $i) !== 1) {
                    throw new RuleError("Bad number at position {$i}.");
                }
                $tokens[] = new Token(Token::NUMBER, $m[0], $i);
                $i += strlen($m[0]);

                continue;
            }
            if ($c === '"' || $c === "'") {
                $j = $i + 1;
                $buf = '';
                while ($j < $n && $src[$j] !== $c) {
                    if ($src[$j] === '\\' && $j + 1 < $n) {
                        $j++;
                    }
                    $buf .= $src[$j];
                    $j++;
                }
                if ($j >= $n) {
                    throw new RuleError("Unterminated string at position {$i}.");
                }
                $tokens[] = new Token(Token::STRING, $buf, $i);
                $i = $j + 1;

                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                if (preg_match('/\G[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*/', $src, $m, 0, $i) !== 1) {
                    throw new RuleError("Bad name at position {$i}.");
                }
                $word = $m[0];
                if ($word === 'not' && preg_match('/\Gnot\s+in\b/', $src, $mm, 0, $i) === 1) {
                    $tokens[] = new Token(Token::OP, 'not in', $i);
                    $i += strlen($mm[0]);

                    continue;
                }
                $tokens[] = in_array($word, self::WORD_OPERATORS, true) ? new Token(Token::OP, $word, $i) : new Token(Token::NAME, $word, $i);
                $i += strlen($word);

                continue;
            }
            if (in_array($c, ['(', ')', '[', ']', ','], true)) {
                $tokens[] = new Token(Token::PUNCT, $c, $i);
                $i++;

                continue;
            }
            foreach (self::OPERATORS as $op) {
                if (substr($src, $i, strlen($op)) === $op) {
                    $tokens[] = new Token(Token::OP, match ($op) {
                        '&&' => 'and', '||' => 'or', '!' => 'not', default => $op,
                    }, $i);
                    $i += strlen($op);

                    continue 2;
                }
            }
            throw new RuleError("Unexpected character '{$c}' at position {$i}.");
        }
        $tokens[] = new Token(Token::END, '', $n);

        return $tokens;
    }
}
