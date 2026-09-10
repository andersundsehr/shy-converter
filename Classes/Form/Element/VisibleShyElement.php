<?php

declare(strict_types=1);

namespace Andersundsehr\ShyConverter\Form\Element;

use Andersundsehr\ShyConverter\Utility\SoftHyphenConverter;
use Override;
use TYPO3\CMS\Backend\Form\Element\InputTextElement;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

final class VisibleShyElement extends InputTextElement
{
    private const JAVASCRIPT_MODULE = '@andersundsehr/shy-converter/visible-shy.js';

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function render(): array
    {
        $itemValue = $this->data['parameterArray']['itemFormElValue'] ?? null;
        $config = &$this->data['parameterArray']['fieldConf']['config'];

        // The visible notation consists of five characters while the persisted
        // soft hyphen is a single character. Server-side TCA processing still
        // applies the original min/max configuration to the submitted value.
        unset($config['min'], $config['max']);

        if ($config['readOnly'] ?? false) {
            if (is_string($itemValue)) {
                $this->data['parameterArray']['itemFormElValue'] = SoftHyphenConverter::makeVisible($itemValue);
            }
            return parent::render();
        }

        $result = parent::render();

        // InputTextElement normally synchronizes its visible field to the named
        // hidden field. visibleShy owns that synchronization because both fields
        // intentionally contain different representations of the same value.
        $escapedItemName = htmlspecialchars((string)$this->data['parameterArray']['itemFormElName']);
        $visibleInputPattern = '/(<input\\b(?=[^>]*\\bdata-formengine-input-name="'
            . preg_quote($escapedItemName, '/')
            . '")[^>]*?)\\sdata-formengine-input-params="[^"]*"/';
        $replacementCount = 0;
        $html = preg_replace(
            $visibleInputPattern,
            '$1',
            (string)$result['html'],
            1,
            $replacementCount,
        );
        if (!is_string($html) || $replacementCount !== 1) {
            throw new \UnexpectedValueException(
                'Could not disable the default FormEngine synchronization for the visibleShy input.',
                1789027200,
            );
        }

        $result['html'] = $html;
        $result['javaScriptModules'][] = JavaScriptModuleInstruction::create(
            self::JAVASCRIPT_MODULE,
            'initialize',
        )->invoke(null, (string)$this->data['parameterArray']['itemFormElName']);

        return $result;
    }
}
