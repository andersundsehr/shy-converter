<?php

declare(strict_types=1);

namespace Andersundsehr\ShyConverter\Form\Element;

use Andersundsehr\ShyConverter\Utility\SoftHyphenConverter;
use Override;
use TYPO3\CMS\Backend\Form\Element\InputTextElement;

final class VisibleShyElement extends InputTextElement
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function render(): array
    {
        $itemValue = $this->data['parameterArray']['itemFormElValue'] ?? null;
        if (is_string($itemValue)) {
            $this->data['parameterArray']['itemFormElValue'] = SoftHyphenConverter::makeVisible($itemValue);
        }

        // The visible notation consists of five characters while the persisted
        // soft hyphen is a single character. Let DataHandler enforce min/max
        // after the value has been converted back to U+00AD.
        unset(
            $this->data['parameterArray']['fieldConf']['config']['min'],
            $this->data['parameterArray']['fieldConf']['config']['max'],
        );

        return parent::render();
    }
}
