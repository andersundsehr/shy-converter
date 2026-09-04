# shy-converter

This extension converts valid and malformed HTML soft hyphens (`&amp;shy;` and `&amp;shy`) to their UTF-8 equivalent and includes a database migration to update existing data.

## Visible soft hyphens in backend input fields

Plain TCA input fields without an explicit `renderType` automatically use the
`visibleShy` render type. Stored UTF-8 soft hyphens (`U+00AD`) are displayed as
the visible string `&amp;shy;`, so editors can find, add, or remove them.

When a record is saved through TYPO3's DataHandler, both `&amp;shy;` and the
malformed variant `&amp;shy` are converted to `U+00AD` before TCA length limits are
applied. Input fields with another explicit render type and fields inside
FlexForms are not changed automatically.

The render type can also be assigned explicitly:

```php
'config' => [
    'type' => 'input',
    'renderType' => 'visibleShy',
],
```
