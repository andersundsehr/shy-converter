<?php

declare(strict_types=1);

namespace Andersundsehr\ShyConverter\Hooks;

use Andersundsehr\ShyConverter\Form\FormDataProvider\VisibleShyFormDataProvider;
use Andersundsehr\ShyConverter\Utility\SoftHyphenConverter;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\MathUtility;

final class DataHandlerHook
{
    public function __construct(
        private readonly TcaSchemaFactory $tcaSchemaFactory,
    ) {
    }

    /**
     * @param array<array-key, mixed> $incomingFieldArray
     */
    // phpcs:disable PSR1.Methods.CamelCapsMethodName -- Method name is defined by TYPO3's legacy hook API.
    public function processDatamap_preProcessFieldArray(
        array &$incomingFieldArray,
        string $table,
        string|int $id,
        DataHandler $dataHandler,
    ): void {
        if (!$this->tcaSchemaFactory->has($table)) {
            return;
        }

        $schema = $this->tcaSchemaFactory->get($table);
        $subSchema = null;
        if ($schema->supportsSubSchema()) {
            $record = [];
            if (MathUtility::canBeInterpretedAsInteger($id)) {
                $persistedRecord = BackendUtility::getRecord($table, (int)$id);
                if (is_array($persistedRecord)) {
                    $record = $persistedRecord;
                }
            }
            $record = array_replace($record, $incomingFieldArray);
            $recordType = BackendUtility::getTCAtypeValue($table, $record);
            $subSchema = $schema->hasSubSchema($recordType) ? $schema->getSubSchema($recordType) : null;
        }

        foreach ($incomingFieldArray as $fieldName => &$value) {
            if (!is_string($fieldName) || !is_string($value) || !$schema->hasField($fieldName)) {
                continue;
            }

            $field = $subSchema?->hasField($fieldName) === true
                ? $subSchema->getField($fieldName)
                : $schema->getField($fieldName);
            $config = $field->getConfiguration();
            if (($config['type'] ?? null) !== 'input') {
                continue;
            }

            $renderType = $config['renderType'] ?? null;
            if (
                array_key_exists('renderType', $config)
                && $renderType !== VisibleShyFormDataProvider::RENDER_TYPE
            ) {
                continue;
            }

            $value = SoftHyphenConverter::convertToUtf8($value);
        }
        unset($value);
    }
    // phpcs:enable
}
