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
    /**
     * Largest exponent accepted in scientific notation.
     *
     * An exponent expands to that many positional digits, so without a ceiling
     * twelve characters ("1e1000000000") allocate a gigabyte and kill the
     * process with a fatal error no caller can catch. This sits far past the
     * float range (~1e±324) and past any plausible decimal need; a value that
     * genuinely wants more digits can be written out positionally, where the
     * input is as large as the result and nothing is amplified.
     */
    private const MAX_EXPONENT = 100_000;

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

            // Render the fewest significant digits that read back as this same
            // double, so nothing is invented and nothing is dropped. json_encode()
            // would do this too, but only while serialize_precision is -1, and a
            // library cannot rely on a consuming app's php.ini.
            //
            // %e precision counts digits after the point, one fewer than the
            // significant digits rendered, and 17 needs no test: IEEE 754
            // guarantees every double survives a round trip there.
            //
            foreach ([15, 16] as $digits) {
                $rendered = sprintf('%.'.($digits - 1).'e', $value);

                if ((float) $rendered === $value) {
                    return $rendered;
                }
            }

            return sprintf('%.16e', $value);
        }

        return trim($value);
    }

    /**
     * Validate and canonicalise a decimal string: expand any exponent, strip a
     * leading '+', collapse '-0' to '0', ensure a leading digit, and trim
     * redundant trailing zeros.
     *
     * Scientific notation is accepted because it is what a float cast, a JSON
     * decode or a database driver hands back — "1.0E-5" names the same exact
     * value as "0.00001" and is expanded to it positionally.
     *
     * @return numeric-string
     */
    public static function normalize(string $value): string
    {
        if (preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$/', $value) !== 1) {
            throw new InvalidArgumentException("[{$value}] is not a valid decimal number.");
        }

        if (stripos($value, 'e') !== false) {
            $value = self::expand($value);
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
     * Expand a scientific rendering ("1.2345678901235e+20", "12.5E3") into a
     * plain positional decimal string, exactly — pure string surgery, no
     * further arithmetic, so no precision is lost at either extreme.
     */
    private static function expand(string $scientific): string
    {
        $position = stripos($scientific, 'e');

        if ($position === false) {
            // Unreachable: callers check for an exponent first.
            //
            throw new InvalidArgumentException("[{$scientific}] is not a scientific rendering.");
        }

        $mantissa = substr($scientific, 0, $position);
        $exponent = (int) substr($scientific, $position + 1);

        if ($exponent > self::MAX_EXPONENT || $exponent < -self::MAX_EXPONENT) {
            throw new InvalidArgumentException("[{$scientific}] has an exponent beyond the supported ±".self::MAX_EXPONENT.'; write the value out positionally if the digits are really wanted.');
        }

        $negative = str_starts_with($mantissa, '-');
        $mantissa = ltrim($mantissa, '+-');

        // The point starts after the mantissa's integer digits and the exponent
        // shifts it from there. %e always renders exactly one integer digit,
        // but a hand-written "12.5e3" or "125e1" carries more, so count them
        // rather than assuming.
        //
        $pointPosition = strcspn($mantissa, '.') + $exponent;
        $digits = str_replace('.', '', $mantissa);

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
