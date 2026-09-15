<?php

declare(strict_types=1);

namespace Vellum\Support;

/**
 * sRGB colour maths for the theme tokens: parsing, relative luminance, and
 * WCAG contrast. Used to pick a readable label for a configured accent and by
 * the preset contrast test.
 *
 * @phpstan-type Rgba array{float, float, float, float}
 */
final class Color
{
    /**
     * Parse a hex, hsl()/hsla() or oklch() colour into [r, g, b, alpha].
     *
     * @return Rgba|null
     */
    public static function parse(string $value): ?array
    {
        $value = trim($value);

        if (preg_match('/^#([0-9a-f]{3,8})$/i', $value, $m) === 1) {
            return self::fromHex($m[1]);
        }

        if (preg_match('/^hsla?\(([^)]*)\)$/i', $value, $m) === 1) {
            return self::fromHsl($m[1]);
        }

        if (preg_match('/^oklch\(([^)]*)\)$/i', $value, $m) === 1) {
            return self::fromOklch($m[1]);
        }

        return null;
    }

    /**
     * WCAG 2.1 relative luminance.
     *
     * @param  Rgba  $rgba
     */
    public static function luminance(array $rgba): float
    {
        $channel = static function (float $c): float {
            $c /= 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($rgba[0]) + 0.7152 * $channel($rgba[1]) + 0.0722 * $channel($rgba[2]);
    }

    /**
     * WCAG contrast ratio, compositing a translucent foreground over the background first.
     *
     * @param  Rgba  $foreground
     * @param  Rgba  $background
     */
    public static function contrast(array $foreground, array $background): float
    {
        $composited = self::over($foreground, $background);
        $a = self::luminance($composited);
        $b = self::luminance($background);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Composite a foreground over an opaque background.
     *
     * @param  Rgba  $foreground
     * @param  Rgba  $background
     * @return Rgba
     */
    public static function over(array $foreground, array $background): array
    {
        $alpha = $foreground[3];

        if ($alpha >= 1.0) {
            return $foreground;
        }

        return [
            $foreground[0] * $alpha + $background[0] * (1 - $alpha),
            $foreground[1] * $alpha + $background[1] * (1 - $alpha),
            $foreground[2] * $alpha + $background[2] * (1 - $alpha),
            1.0,
        ];
    }

    /**
     * The label colour that reads best on the given fill.
     */
    public static function readableOn(string $fill, string $light = '#ffffff', string $dark = '#171717'): string
    {
        $background = self::parse($fill);

        if ($background === null) {
            return $light;
        }

        $onLight = self::parse($light);
        $onDark = self::parse($dark);

        if ($onLight === null || $onDark === null) {
            return $light;
        }

        return self::contrast($onLight, $background) >= self::contrast($onDark, $background) ? $light : $dark;
    }

    /**
     * @return Rgba|null
     */
    private static function fromHex(string $hex): ?array
    {
        $length = strlen($hex);

        if ($length === 3 || $length === 4) {
            $expanded = '';

            foreach (str_split($hex) as $char) {
                $expanded .= $char.$char;
            }

            $hex = $expanded;
            $length = strlen($hex);
        }

        if ($length !== 6 && $length !== 8) {
            return null;
        }

        return [
            (float) hexdec(substr($hex, 0, 2)),
            (float) hexdec(substr($hex, 2, 2)),
            (float) hexdec(substr($hex, 4, 2)),
            $length === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0,
        ];
    }

    /**
     * @return Rgba|null
     */
    private static function fromHsl(string $args): ?array
    {
        $parts = self::arguments($args);

        if (count($parts) < 3) {
            return null;
        }

        $h = (float) rtrim($parts[0], 'deg');
        $s = (float) rtrim($parts[1], '%') / 100;
        $l = (float) rtrim($parts[2], '%') / 100;
        $alpha = isset($parts[3]) ? self::alpha($parts[3]) : 1.0;

        $f = static function (float $n) use ($h, $s, $l): float {
            $k = fmod($n + $h / 30, 12);
            $k = $k < 0 ? $k + 12 : $k;
            $a = $s * min($l, 1 - $l);

            return ($l - $a * max(-1, min($k - 3, min(9 - $k, 1)))) * 255;
        };

        return [$f(0), $f(8), $f(4), $alpha];
    }

    /**
     * @return Rgba|null
     */
    private static function fromOklch(string $args): ?array
    {
        $parts = self::arguments($args);

        if (count($parts) < 3) {
            return null;
        }

        $l = str_contains($parts[0], '%') ? (float) rtrim($parts[0], '%') / 100 : (float) $parts[0];
        $c = (float) $parts[1];
        $h = (float) rtrim($parts[2], 'deg');
        $alpha = isset($parts[3]) ? self::alpha($parts[3]) : 1.0;

        $hr = deg2rad($h);
        $a = $c * cos($hr);
        $b = $c * sin($hr);

        $lms = [
            ($l + 0.3963377774 * $a + 0.2158037573 * $b) ** 3,
            ($l - 0.1055613458 * $a - 0.0638541728 * $b) ** 3,
            ($l - 0.0894841775 * $a - 1.2914855480 * $b) ** 3,
        ];

        $linear = [
            4.0767416621 * $lms[0] - 3.3077115913 * $lms[1] + 0.2309699292 * $lms[2],
            -1.2684380046 * $lms[0] + 2.6097574011 * $lms[1] - 0.3413193965 * $lms[2],
            -0.0041960863 * $lms[0] - 0.7034186147 * $lms[1] + 1.7076147010 * $lms[2],
        ];

        $encode = static function (float $v): float {
            $v = max(0.0, min(1.0, $v));

            return ($v <= 0.0031308 ? 12.92 * $v : 1.055 * $v ** (1 / 2.4) - 0.055) * 255;
        };

        return [$encode($linear[0]), $encode($linear[1]), $encode($linear[2]), $alpha];
    }

    /**
     * @return list<string>
     */
    private static function arguments(string $args): array
    {
        $args = str_replace(['/', ','], ' ', $args);
        $parts = preg_split('/\s+/', trim($args)) ?: [];

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private static function alpha(string $value): float
    {
        return str_contains($value, '%') ? (float) rtrim($value, '%') / 100 : (float) $value;
    }
}
