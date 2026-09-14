# shy-converter

This extension converts HTML soft hyphens (`&shy;` and the malformed `&shy`) and non-breaking spaces (`&nbsp;`) to their UTF-8 equivalents. It includes a database migration to update existing data.

## Installation

Install the extension in a TYPO3 13.4 or 14 project:

```bash
composer require andersundsehr/shy-converter
```

Optionally migrate existing values after reviewing the affected records:

```bash
vendor/bin/typo3 shy-converter:migrate --dry-run
vendor/bin/typo3 shy-converter:migrate
```

## Visible special characters in backend input fields

Plain TCA input fields without an explicit `renderType` automatically use the
`visibleShy` render type. Stored UTF-8 soft hyphens (`U+00AD`) and non-breaking
spaces (`U+00A0`) are displayed as the visible strings `&amp;shy;` and `&amp;nbsp;`,
so editors can find, add, or remove them.

The render type uses TYPO3's hidden input for the persisted value. Its backend
JavaScript converts `&amp;shy;` and the malformed variant `&amp;shy` to `U+00AD`, and
`&amp;nbsp;` to `U+00A0`, while editors type and immediately before the form is
submitted. Literal entity-like text is displayed with an `&amp;amp;` prefix and
stored unchanged, matching TYPO3's Visual Editor behavior. The original hidden
value remains untouched until the JavaScript is initialized, preventing an
unconverted visible value from being submitted if initialization fails.

The conversion is intentionally scoped to forms rendered with `visibleShy`.
Direct or programmatic DataHandler calls are not modified. Input fields with
another explicit render type and fields inside FlexForms are not changed
automatically.

The render type can also be assigned explicitly:

```php
'config' => [
    'type' => 'input',
    'renderType' => 'visibleShy',
],
```
