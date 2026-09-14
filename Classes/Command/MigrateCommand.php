<?php

declare(strict_types=1);

namespace Andersundsehr\ShyConverter\Command;

use Andersundsehr\ShyConverter\Utility\SoftHyphenConverter;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsCommand(
    name: 'shy-converter:migrate',
    description: 'Convert supported HTML entities in input fields to their UTF-8 characters',
    aliases: ['sc:m'],
)]
final class MigrateCommand extends Command
{
    /** @var list<string> */
    private const TEXT_COLUMN_TYPES = [
        Types::ASCII_STRING,
        Types::STRING,
        Types::TEXT,
    ];

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Show affected fields and records without changing the database',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');

        $io->title('Special character database migration');

        try {
            $fieldsByTable = $this->findEligibleFields($io);
        } catch (Throwable $throwable) {
            $io->error('Could not inspect the database schema: ' . $throwable->getMessage());
            return Command::FAILURE;
        }

        $resultRows = [];
        $affectedFieldValues = 0;

        foreach ($fieldsByTable as $tableName => $fieldNames) {
            try {
                $tableResult = $this->processTable($tableName, $fieldNames, $dryRun);
            } catch (Throwable $throwable) {
                $io->error(sprintf(
                    'Migration failed for table "%s": %s',
                    $tableName,
                    $throwable->getMessage(),
                ));
                return Command::FAILURE;
            }

            foreach ($tableResult as $fieldName => $affectedRows) {
                if ($affectedRows === 0) {
                    continue;
                }

                $resultRows[] = [$tableName, $fieldName, $affectedRows];
                $affectedFieldValues += $affectedRows;
            }
        }

        if ($resultRows !== []) {
            $io->table(['Table', 'Field', 'Affected records'], $resultRows);
        }

        if ($dryRun) {
            $io->note(sprintf(
                'Dry run: %d field value(s) would be converted. No data was changed.',
                $affectedFieldValues,
            ));
        } else {
            $io->success(sprintf('%d field value(s) converted.', $affectedFieldValues));
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, list<string>>
     * @throws \Doctrine\DBAL\Exception
     */
    private function findEligibleFields(SymfonyStyle $io): array
    {
        $fieldsByTable = [];

        foreach ($GLOBALS['TCA'] ?? [] as $tableName => $tableConfiguration) {
            if (!is_string($tableName) || $tableName === '' || !is_array($tableConfiguration['columns'] ?? null)) {
                continue;
            }

            $connection = $this->connectionPool->getConnectionForTable($tableName);
            $schemaManager = $connection->createSchemaManager();

            if (!$schemaManager->tablesExist([$tableName])) {
                $io->warning(sprintf('Skipping TCA table "%s" because it does not exist.', $tableName));
                continue;
            }

            $databaseColumns = [];
            foreach ($schemaManager->introspectTableColumnsByUnquotedName($tableName) as $databaseColumn) {
                $databaseColumns[$databaseColumn->getObjectName()->getIdentifier()->getValue()] = $databaseColumn;
            }

            foreach ($tableConfiguration['columns'] as $fieldName => $fieldConfiguration) {
                if (!is_string($fieldName) || !is_array($fieldConfiguration)) {
                    continue;
                }

                $fieldConfig = $fieldConfiguration['config'] ?? null;
                if (
                    !is_array($fieldConfig)
                    || ($fieldConfig['type'] ?? null) !== 'input'
                    || array_key_exists('renderType', $fieldConfig)
                ) {
                    continue;
                }

                if (!isset($databaseColumns[$fieldName])) {
                    $io->warning(sprintf(
                        'Skipping TCA field "%s.%s" because the database column does not exist.',
                        $tableName,
                        $fieldName,
                    ));
                    continue;
                }

                $columnType = Type::lookupName($databaseColumns[$fieldName]->getType());
                if (!in_array($columnType, self::TEXT_COLUMN_TYPES, true)) {
                    continue;
                }

                $fieldsByTable[$tableName][] = $fieldName;
            }
        }

        return $fieldsByTable;
    }

    /**
     * @param list<string> $fieldNames
     * @return array<string, int>
     * @throws \Doctrine\DBAL\Exception
     * @throws \Throwable
     */
    private function processTable(string $tableName, array $fieldNames, bool $dryRun): array
    {
        $connection = $this->connectionPool->getConnectionForTable($tableName);

        if ($dryRun) {
            return $this->countAffectedRows($connection, $tableName, $fieldNames);
        }

        $connection->beginTransaction();

        try {
            $result = [];
            $quotedTableName = $connection->quoteIdentifier($tableName);

            foreach ($fieldNames as $fieldName) {
                $quotedFieldName = $connection->quoteIdentifier($fieldName);
                $result[$fieldName] = (int)$connection->executeStatement(
                    sprintf(
                        'UPDATE %s SET %s = REPLACE('
                        . 'REPLACE(REPLACE(%s, :htmlShySource, :shyReplacement), '
                        . ':malformedHtmlShySource, :shyReplacement), '
                        . ':htmlNbspSource, :nbspReplacement'
                        . ') WHERE LENGTH(REPLACE(%s, :malformedHtmlShySource, \'\')) < LENGTH(%s) '
                        . 'OR LENGTH(REPLACE(%s, :htmlNbspSource, \'\')) < LENGTH(%s)',
                        $quotedTableName,
                        $quotedFieldName,
                        $quotedFieldName,
                        $quotedFieldName,
                        $quotedFieldName,
                        $quotedFieldName,
                        $quotedFieldName,
                    ),
                    [
                        'htmlShySource' => SoftHyphenConverter::HTML_SOFT_HYPHEN,
                        'malformedHtmlShySource' => SoftHyphenConverter::MALFORMED_HTML_SOFT_HYPHEN,
                        'shyReplacement' => SoftHyphenConverter::UTF8_SOFT_HYPHEN,
                        'htmlNbspSource' => SoftHyphenConverter::HTML_NON_BREAKING_SPACE,
                        'nbspReplacement' => SoftHyphenConverter::UTF8_NON_BREAKING_SPACE,
                    ],
                    [
                        'htmlShySource' => Connection::PARAM_STR,
                        'malformedHtmlShySource' => Connection::PARAM_STR,
                        'shyReplacement' => Connection::PARAM_STR,
                        'htmlNbspSource' => Connection::PARAM_STR,
                        'nbspReplacement' => Connection::PARAM_STR,
                    ],
                );
            }

            $connection->commit();
            return $result;
        } catch (Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }
    }

    /**
     * @param list<string> $fieldNames
     * @return array<string, int>
     */
    private function countAffectedRows(Connection $connection, string $tableName, array $fieldNames): array
    {
        $result = [];
        $quotedTableName = $connection->quoteIdentifier($tableName);

        foreach ($fieldNames as $fieldName) {
            $quotedFieldName = $connection->quoteIdentifier($fieldName);
            $result[$fieldName] = (int)$connection->fetchOne(
                sprintf(
                    'SELECT COUNT(*) FROM %s '
                    . 'WHERE LENGTH(REPLACE(%s, :malformedHtmlShySource, \'\')) < LENGTH(%s) '
                    . 'OR LENGTH(REPLACE(%s, :htmlNbspSource, \'\')) < LENGTH(%s)',
                    $quotedTableName,
                    $quotedFieldName,
                    $quotedFieldName,
                    $quotedFieldName,
                    $quotedFieldName,
                ),
                [
                    'malformedHtmlShySource' => SoftHyphenConverter::MALFORMED_HTML_SOFT_HYPHEN,
                    'htmlNbspSource' => SoftHyphenConverter::HTML_NON_BREAKING_SPACE,
                ],
                [
                    'malformedHtmlShySource' => Connection::PARAM_STR,
                    'htmlNbspSource' => Connection::PARAM_STR,
                ],
            );
        }

        return $result;
    }
}
