<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Tecniplast\Import;

use App\Entity\Account;
use App\Entity\Cage;
use App\Entity\CageHcmMeasure;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Entity\HomeCageMonitoring;
use App\Entity\Subject;
use App\Entity\TimeSeries;
use App\Entity\Variable;
use App\Enum\HcmDataType;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DvcImportService implements DvcImportServiceInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function importFromZip(string $zipFilePath, Experiment $experiment, ConnectedApp $connectedApp, int|string $taskId): DvcImportResult
    {
        $archive = new \ZipArchive();
        $openResult = $archive->open($zipFilePath);
        if (true !== $openResult) {
            throw new \RuntimeException(\sprintf('Unable to open DVC archive "%s".', $zipFilePath));
        }

        $csvContent = null;
        for ($index = 0; $index < $archive->numFiles; ++$index) {
            $name = $archive->getNameIndex($index);
            if (!\is_string($name) || !str_ends_with(strtolower($name), '.csv')) {
                continue;
            }

            $csvContent = $archive->getFromIndex($index);
            if (\is_string($csvContent)) {
                break;
            }
        }

        $archive->close();

        if (!\is_string($csvContent) || '' === trim($csvContent)) {
            throw new \RuntimeException('No CSV payload found in DVC archive.');
        }

        return $this->importFromCsv($csvContent, $experiment, $connectedApp, $taskId);
    }

    public function importFromCsv(string $csvContent, Experiment $experiment, ConnectedApp $connectedApp, int|string $taskId): DvcImportResult
    {
        $account = $connectedApp->getAccount();
        if ((string) $experiment->getAccount()->getId() !== (string) $account->getId()) {
            throw new \RuntimeException('Experiment and connected app must belong to the same account.');
        }

        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Unable to allocate CSV buffer.');
        }

        fwrite($handle, $csvContent);
        rewind($handle);

        $headerRow = fgetcsv($handle);
        if (!\is_array($headerRow)) {
            fclose($handle);

            throw new \RuntimeException('DVC CSV payload is missing header row.');
        }

        $normalizedHeaderRow = array_map(static fn (?string $value): string => (string) $value, $headerRow);

        $headerMap = $this->buildHeaderMap($normalizedHeaderRow);
        $result = new DvcImportResult();

        while (($row = fgetcsv($handle)) !== false) {
            ++$result->processedRows;

            if (!$this->importRow($row, $headerMap, $experiment, $connectedApp, $taskId, $account)) {
                ++$result->skippedRows;
                continue;
            }

            ++$result->importedRows;
        }

        fclose($handle);
        $this->entityManager->flush();

        return $result;
    }

    /**
     * @param array<int, string> $headerRow
     *
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $headerMap = [];
        foreach ($headerRow as $index => $columnName) {
            $headerMap[$this->normalizeColumnName((string) $columnName)] = $index;
        }

        foreach (['recorded_at', 'cage_external_id', 'metric_external_id', 'metric_name', 'value'] as $requiredColumn) {
            if (!\array_key_exists($requiredColumn, $headerMap)) {
                throw new \RuntimeException(\sprintf('DVC CSV payload is missing required column "%s".', $requiredColumn));
            }
        }

        return $headerMap;
    }

    /**
     * @param array<int, string|null> $row
     * @param array<string, int>      $headerMap
     */
    private function importRow(array $row, array $headerMap, Experiment $experiment, ConnectedApp $connectedApp, int|string $taskId, Account $account): bool
    {
        $cageExternalId = trim((string) $this->rowValue($row, $headerMap, 'cage_external_id'));
        $metricExternalId = trim((string) $this->rowValue($row, $headerMap, 'metric_external_id'));
        $metricName = trim((string) $this->rowValue($row, $headerMap, 'metric_name'));
        $recordedAtRaw = trim((string) $this->rowValue($row, $headerMap, 'recorded_at'));
        $valueRaw = trim((string) $this->rowValue($row, $headerMap, 'value'));

        if ('' === $cageExternalId || '' === $metricExternalId || '' === $metricName || '' === $recordedAtRaw || '' === $valueRaw) {
            return false;
        }

        $cage = $this->entityManager->getRepository(Cage::class)->findOneBy([
            'externalId' => $cageExternalId,
            'account' => $account,
        ]);
        if (!$cage instanceof Cage) {
            return false;
        }

        $subject = $this->firstSubjectForAccount($cage, $account);
        if (!$subject instanceof Subject) {
            return false;
        }

        try {
            $recordedAt = new \DateTimeImmutable($recordedAtRaw);
        } catch (\Exception) {
            return false;
        }

        if (!is_numeric($valueRaw)) {
            return false;
        }

        $value = (float) $valueRaw;
        $unit = trim((string) $this->rowValue($row, $headerMap, 'unit'));
        $dataType = $this->resolveDataType((string) $this->rowValue($row, $headerMap, 'data_type'), $metricName);

        $variable = $this->resolveVariable($account, $metricExternalId, $metricName);
        $homeCageMonitoring = $this->resolveHomeCageMonitoring($cage, $experiment, $account);

        $timeSeries = (new TimeSeries())
            ->setAccount($account)
            ->setSubject($subject)
            ->setExperiment($experiment)
            ->setVariable($variable)
            ->setRecordedAt($recordedAt)
            ->setValue((string) $value)
            ->setUnit('' === $unit ? null : $unit)
            ->setSourceTaskId((string) $taskId)
            ->setSourceAppCode($connectedApp->getCode()->value)
        ;
        $this->entityManager->persist($timeSeries);

        $measure = (new CageHcmMeasure())
            ->setAccount($account)
            ->setCage($cage)
            ->setVariable($variable)
            ->setRecordedAt($recordedAt)
            ->setValue($value)
            ->setDataType($dataType)
            ->setHomeCageMonitoring($homeCageMonitoring)
            ->setSourceTaskId((string) $taskId)
            ->setSourceAppCode($connectedApp->getCode()->value)
        ;
        $this->entityManager->persist($measure);

        return true;
    }

    /**
     * @param array<int, string|null> $row
     * @param array<string, int>      $headerMap
     */
    private function rowValue(array $row, array $headerMap, string $column): ?string
    {
        if (!\array_key_exists($column, $headerMap)) {
            return null;
        }

        $index = $headerMap[$column];

        return isset($row[$index]) ? (string) $row[$index] : null;
    }

    private function normalizeColumnName(string $columnName): string
    {
        $normalized = trim(strtolower($columnName));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return preg_replace('/_+/', '_', $normalized) ?? $normalized;
    }

    private function resolveDataType(string $rawType, string $metricName): HcmDataType
    {
        $normalized = strtolower(trim($rawType));
        if ('' === $normalized) {
            $normalized = strtolower($metricName);
        }

        if (str_contains($normalized, 'food')) {
            return HcmDataType::FoodIntake;
        }

        return HcmDataType::Activity;
    }

    private function resolveVariable(Account $account, string $metricExternalId, string $metricName): Variable
    {
        $variable = $this->entityManager->getRepository(Variable::class)->findOneBy([
            'tecniplastExternalId' => $metricExternalId,
            'account' => $account,
        ]);

        if (!$variable instanceof Variable) {
            $variable = $this->entityManager->getRepository(Variable::class)->findOneBy([
                'name' => $metricName,
                'account' => $account,
            ]);
        }

        if (!$variable instanceof Variable) {
            $variable = (new Variable())
                ->setAccount($account)
                ->setName($metricName)
            ;
            $this->entityManager->persist($variable);
        }

        if (null === $variable->getTecniplastExternalId()) {
            $variable->setTecniplastExternalId($metricExternalId);
        }

        return $variable;
    }

    private function resolveHomeCageMonitoring(Cage $cage, Experiment $experiment, Account $account): HomeCageMonitoring
    {
        $homeCageMonitoring = $cage->getHomeCageMonitoring();
        if ($homeCageMonitoring instanceof HomeCageMonitoring) {
            return $homeCageMonitoring;
        }

        $homeCageMonitoring = (new HomeCageMonitoring())
            ->setAccount($account)
            ->setExperiment($experiment)
            ->setSystem('Tecniplast DVC')
        ;
        $this->entityManager->persist($homeCageMonitoring);

        $cage->setHomeCageMonitoring($homeCageMonitoring);
        $this->entityManager->persist($cage);

        return $homeCageMonitoring;
    }

    private function firstSubjectForAccount(Cage $cage, Account $account): ?Subject
    {
        foreach ($cage->getSubjects() as $subject) {
            if ((string) $subject->getAccount()->getId() === (string) $account->getId()) {
                return $subject;
            }
        }

        return null;
    }
}
