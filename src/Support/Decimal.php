<?php

declare(strict_types=1);

namespace Penangites\Number\Support;

use InvalidArgumentException;

/**
 * Shared helpers for canonical decimal strings.
 *
 * @internal
 */
final class Decimal
{
    public static function stringify(float|int|string $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            // Literal names, not a string cast: casting NAN emits a warning
            // ("unexpected NAN value was coerced to string") before
            // normalize() gets to reject it with a clear error.
            //
            if (is_nan($value)) {
                return 'NAN';
            }

            if (! is_finite($value)) {
                return $value > 0 ? 'INF' : '-INF';
            }

            if ($value === 0.0) {
                return '0';
            }

            // Render floats at 14 significant digits — PHP's own default
            // precision, one digit under the double's 15-digit round-trip
            // guarantee, so one-ulp arithmetic noise stays hidden
            // (0.1 + 0.2 -> "0.3"). Scientific notation is the only sprintf
            // form that rounds significant digits at any magnitude (%F cannot
            // round left of the decimal point, and its precision is capped at
            // 53 digits, which turns subnormals into zero), so render
            // scientific first and expand the exponent positionally.
            //
            return self::expand(sprintf('%.13e', $value));
        }

        return trim($value);
    }

    /**
     * Validate and canonicalise a decimal string: strip a leading '+', collapse
     * '-0' to '0', ensure a leading digit, and trim redundant trailing zeros.
     *
     * @return numeric-string
     */
    public static function normalize(string $value): string
    {
        if (preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $value) !== 1) {
            throw new InvalidArgumentException("[{$value}] is not a valid decimal number.");
        }

        $negative = str_starts_with($value, '-');
        $digits = ltrim($value, '+-');

        if (str_contains($digits, '.')) {
            $digits = rtrim($digits, '0');
            $digits = rtrim($digits, '.');
        }

        if (str_starts_with($digits, '.')) {
            $digits = '0'.$digits;
        }

        $digits = ltrim($digits, '0');
        $digits = ($digits === '' || str_starts_with($digits, '.')) ? '0'.$digits : $digits;

        if ($digits === '0') {
            return '0';
        }

        $result = $negative ? '-'.$digits : $digits;

        if (! is_numeric($result)) {
            // Unreachable: the regex above and the digit-only manipulations
            // above guarantee a numeric string. This guard exists so the
            // return type can be trusted as `numeric-string` by callers.
            //
            throw new InvalidArgumentException("[{$result}] is not a valid decimal number.");
        }

        return $result;
    }

    public static function scaleOf(string $decimal): int
    {
        $dot = strrchr($decimal, '.');

        return $dot === false ? 0 : strlen($dot) - 1;
    }

    /**
     * Expand a sprintf scientific rendering ("1.2345678901235e+20") into a
     * plain positional decimal string, exactly — pure string surgery, no
     * further arithmetic, so no precision is lost at either extreme.
     */
    private static function expand(string $scientific): string
    {
        $position = strpos($scientific, 'e');

        if ($position === false) {
            // Unreachable: %e output always contains an exponent.
            //
            throw new InvalidArgumentException("[{$scientific}] is not a scientific rendering.");
        }

        $mantissa = substr($scientific, 0, $position);
        $exponent = (int) substr($scientific, $position + 1);

        $negative = str_starts_with($mantissa, '-');
        $digits = str_replace('.', '', ltrim($mantissa, '+-'));
        $pointPosition = 1 + $exponent;

        if ($pointPosition <= 0) {
            $result = '0.'.str_repeat('0', -$pointPosition).$digits;
        } elseif ($pointPosition >= strlen($digits)) {
            $result = $digits.str_repeat('0', $pointPosition - strlen($digits));
        } else {
            $result = substr($digits, 0, $pointPosition).'.'.substr($digits, $pointPosition);
        }

        return $negative ? '-'.$result : $result;
    }
}
