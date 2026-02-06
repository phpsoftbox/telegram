<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime\Text;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function preg_match;
use function preg_replace_callback;
use function str_contains;
use function str_starts_with;
use function stripcslashes;
use function strlen;
use function strpos;
use function strtolower;
use function strtr;
use function substr;
use function trim;

final readonly class TextFormatter
{
    public function __construct(
        private ?ContentVariableRegistry $variables = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function format(string $template, array $context = []): string
    {
        if ($template === '') {
            return '';
        }

        $resolved = $this->variables?->resolve($context) ?? $context;
        $template = $this->renderIfBlocks($template, $resolved);

        $template = preg_replace_callback(
            '/\{%\s*echo\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*%\}/u',
            static function (array $matches) use ($resolved): string {
                $name = trim((string) ($matches[1] ?? ''));
                if ($name === '' || !array_key_exists($name, $resolved)) {
                    return '';
                }

                return self::stringValue($resolved[$name]);
            },
            $template,
        ) ?? $template;

        if ($resolved === []) {
            return $template;
        }

        $replace = [];
        foreach ($resolved as $key => $value) {
            $name = trim((string) $key);
            if ($name === '') {
                continue;
            }

            $replace['{' . $name . '}'] = self::stringValue($value);
        }

        return strtr($template, $replace);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderIfBlocks(string $template, array $context): string
    {
        $offset    = 0;
        [$content] = $this->renderSegment($template, $context, $offset, []);

        return $content;
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $stopTags
     * @return array{0:string,1:?string}
     */
    private function renderSegment(string $template, array $context, int &$offset, array $stopTags): array
    {
        $output = '';
        $length = strlen($template);

        while ($offset < $length) {
            $tagStart = strpos($template, '{%', $offset);
            if ($tagStart === false) {
                $output .= substr($template, $offset);
                $offset = $length;
                break;
            }

            $output .= substr($template, $offset, $tagStart - $offset);

            $tagEnd = strpos($template, '%}', $tagStart + 2);
            if ($tagEnd === false) {
                $output .= substr($template, $tagStart);
                $offset = $length;
                break;
            }

            $rawTag = substr($template, $tagStart, ($tagEnd + 2) - $tagStart);
            $tag    = trim(substr($template, $tagStart + 2, $tagEnd - ($tagStart + 2)));
            $offset = $tagEnd + 2;

            if ($tag === 'else' || $tag === 'endif') {
                if ($this->contains($stopTags, $tag)) {
                    return [$output, $tag];
                }

                $output .= $rawTag;
                continue;
            }

            if (str_starts_with($tag, 'if ')) {
                $expression            = trim(substr($tag, 3));
                [$ifBody, $closingTag] = $this->renderSegment($template, $context, $offset, ['else', 'endif']);

                $elseBody = '';
                if ($closingTag === 'else') {
                    [$elseBody] = $this->renderSegment($template, $context, $offset, ['endif']);
                }

                $output .= $this->evaluateCondition($expression, $context) ? $ifBody : $elseBody;
                continue;
            }

            $output .= $rawTag;
        }

        return [$output, null];
    }

    /**
     * @param list<string> $items
     */
    private function contains(array $items, string $needle): bool
    {
        foreach ($items as $item) {
            if ($item === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function evaluateCondition(string $expression, array $context): bool
    {
        $expression = $this->stripOuterParentheses(trim($expression));
        if ($expression === '') {
            return false;
        }

        if (str_starts_with($expression, '!')) {
            return !$this->evaluateCondition(substr($expression, 1), $context);
        }

        if (preg_match('/^empty\s*\((.*)\)$/su', $expression, $matches) === 1) {
            $value = $this->resolveValue((string) ($matches[1] ?? ''), $context);

            return $this->isEmptyValue($value);
        }

        $comparison = $this->findTopLevelComparison($expression);
        if ($comparison !== null) {
            [$operator, $position] = $comparison;

            $leftRaw  = trim(substr($expression, 0, $position));
            $rightRaw = trim(substr($expression, $position + strlen($operator)));

            $leftValue  = $this->resolveValue($leftRaw, $context);
            $rightValue = $this->resolveValue($rightRaw, $context);

            return $this->compareValues($leftValue, $rightValue, $operator);
        }

        return $this->boolValue($this->resolveValue($expression, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveValue(string $raw, array $context): mixed
    {
        $value = $this->stripOuterParentheses(trim($raw));
        if ($value === '') {
            return '';
        }

        if (
            strlen($value) >= 2
            && $value[0] === '{'
            && $value[strlen($value) - 1] === '}'
        ) {
            $name = trim(substr($value, 1, -1));
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/u', $name) === 1) {
                return $context[$name] ?? null;
            }
        }

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/u', $value) === 1) {
            return $context[$value] ?? null;
        }

        if (
            strlen($value) >= 2
            && (
                ($value[0] === '\'' && $value[strlen($value) - 1] === '\'')
                || ($value[0] === '"' && $value[strlen($value) - 1] === '"')
            )
        ) {
            return stripcslashes(substr($value, 1, -1));
        }

        $normalized = strtolower($value);
        if ($normalized === 'true') {
            return true;
        }
        if ($normalized === 'false') {
            return false;
        }
        if ($normalized === 'null') {
            return null;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    private function stripOuterParentheses(string $value): string
    {
        $result = trim($value);
        while (
            strlen($result) >= 2
            && $result[0] === '('
            && $result[strlen($result) - 1] === ')'
            && $this->isWrappedByOuterParentheses($result)
        ) {
            $result = trim(substr($result, 1, -1));
        }

        return $result;
    }

    private function isWrappedByOuterParentheses(string $value): bool
    {
        $length = strlen($value);
        $depth  = 0;
        $quote  = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0 && $i < $length - 1) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }

    /**
     * @return array{0:string,1:int}|null
     */
    private function findTopLevelComparison(string $expression): ?array
    {
        $operators = ['===', '!==', '>=', '<=', '==', '!=', '>', '<'];
        $length    = strlen($expression);
        $depth     = 0;
        $quote     = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '\'' || $char === '"') {
                $quote = $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                if ($depth > 0) {
                    $depth--;
                }
                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            foreach ($operators as $operator) {
                if (substr($expression, $i, strlen($operator)) === $operator) {
                    return [$operator, $i];
                }
            }
        }

        return null;
    }

    private function compareValues(mixed $left, mixed $right, string $operator): bool
    {
        return match ($operator) {
            '==='   => $left === $right,
            '!=='   => $left !== $right,
            '=='    => $left == $right,
            '!='    => $left != $right,
            '>'     => $this->compareOrder($left, $right) > 0,
            '<'     => $this->compareOrder($left, $right) < 0,
            '>='    => $this->compareOrder($left, $right) >= 0,
            '<='    => $this->compareOrder($left, $right) <= 0,
            default => false,
        };
    }

    private function compareOrder(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            $leftNumber  = (float) $left;
            $rightNumber = (float) $right;

            return $leftNumber <=> $rightNumber;
        }

        $leftText  = self::stringValue($left);
        $rightText = self::stringValue($right);

        return $leftText <=> $rightText;
    }

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return true;
        }

        if (is_int($value) || is_float($value)) {
            return $value == 0;
        }

        if (is_string($value)) {
            return $value === '' || $value === '0';
        }

        if (is_array($value)) {
            return count($value) === 0;
        }

        return false;
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value > 0;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private static function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
