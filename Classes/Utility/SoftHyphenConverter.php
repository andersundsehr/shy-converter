<?php

declare(strict_types=1);

namespace Andersundsehr\ShyConverter\Utility;

/**
 * Converts soft hyphens between their persisted and editor-facing representations.
 *
 * The database value is the UTF-8 soft-hyphen character, while editors work
 * with the visible HTML entity notation.
 */
final class SoftHyphenConverter
{
    public const HTML_SOFT_HYPHEN = '&shy;';

    public const MALFORMED_HTML_SOFT_HYPHEN = '&shy';

    public const UTF8_SOFT_HYPHEN = "\u{00AD}";

    public static function makeVisible(string $value): string
    {
        return str_replace(self::UTF8_SOFT_HYPHEN, self::HTML_SOFT_HYPHEN, $value);
    }

    public static function convertToUtf8(string $value): string
    {
        return str_replace(
            [self::HTML_SOFT_HYPHEN, self::MALFORMED_HTML_SOFT_HYPHEN],
            self::UTF8_SOFT_HYPHEN,
            $value,
        );
    }
}
