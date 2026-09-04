<?php

declare(strict_types=1);

namespace Andersundsehr\ShyConverter\Utility;

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
