<?php

declare(strict_types=1);

namespace Andersundsehr\ShyConverter\Utility;

/**
 * Converts invisible UTF-8 characters between their persisted and editor-facing representations.
 *
 * The database values are UTF-8 characters, while editors work with visible
 * HTML entity notation.
 */
final class SoftHyphenConverter
{
    public const HTML_SOFT_HYPHEN = '&shy;';

    public const MALFORMED_HTML_SOFT_HYPHEN = '&shy';

    public const UTF8_SOFT_HYPHEN = "\u{00AD}";

    public const HTML_NON_BREAKING_SPACE = '&nbsp;';

    public const UTF8_NON_BREAKING_SPACE = "\u{00A0}";

    public static function makeVisible(string $value): string
    {
        $escapedValue = preg_replace(
            '/&(?=#\d+;|#x[0-9a-fA-F]+;|[a-zA-Z][a-zA-Z0-9]+;)/',
            '&amp;',
            $value,
        );
        if (!is_string($escapedValue)) {
            throw new \UnexpectedValueException('Could not escape HTML entity-like text.', 1790006400);
        }

        return str_replace(
            [self::UTF8_SOFT_HYPHEN, self::UTF8_NON_BREAKING_SPACE],
            [self::HTML_SOFT_HYPHEN, self::HTML_NON_BREAKING_SPACE],
            $escapedValue,
        );
    }

    public static function convertToUtf8(string $value): string
    {
        return str_replace(
            self::MALFORMED_HTML_SOFT_HYPHEN,
            self::UTF8_SOFT_HYPHEN,
            str_ireplace(
                [self::HTML_SOFT_HYPHEN, self::HTML_NON_BREAKING_SPACE],
                [self::UTF8_SOFT_HYPHEN, self::UTF8_NON_BREAKING_SPACE],
                $value,
            ),
        );
    }
}
