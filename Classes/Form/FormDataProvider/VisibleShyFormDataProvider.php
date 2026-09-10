<?php

declare(strict_types=1);

namespace Andersundsehr\ShyConverter\Form\FormDataProvider;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;

/**
 * Applies the visible soft-hyphen render type to eligible TCA input fields.
 *
 * Input fields without an explicitly configured render type use the custom
 * FormEngine element so editors can see and edit soft hyphens.
 */
#[Autoconfigure(public: true)]
final class VisibleShyFormDataProvider implements FormDataProviderInterface
{
    public const RENDER_TYPE = 'visibleShy';

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function addData(array $result): array
    {
        if (!is_array($result['processedTca']['columns'] ?? null)) {
            return $result;
        }

        foreach ($result['processedTca']['columns'] as &$columnConfiguration) {
            if (!is_array($columnConfiguration)) {
                continue;
            }

            $config = $columnConfiguration['config'] ?? null;
            if (
                !is_array($config)
                || ($config['type'] ?? null) !== 'input'
                || array_key_exists('renderType', $config)
            ) {
                continue;
            }

            $columnConfiguration['config']['renderType'] = self::RENDER_TYPE;
        }
        unset($columnConfiguration);

        return $result;
    }
}
