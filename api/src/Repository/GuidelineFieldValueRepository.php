<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\GuidelineFieldValue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<GuidelineFieldValue>
 */
final class GuidelineFieldValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuidelineFieldValue::class);
    }

    /**
     * All stored values for a (resourceType, resourceId, templateId), scoped to
     * the given account for tenant isolation.
     *
     * @return list<GuidelineFieldValue>
     */
    public function findForResource(string $resourceType, Uuid $resourceId, string $templateId, Account $account): array
    {
        return $this->findBy([
            'resourceType' => $resourceType,
            'resourceId' => $resourceId,
            'templateId' => $templateId,
            'account' => $account,
        ]);
    }

    public function findOneForField(
        string $resourceType,
        Uuid $resourceId,
        string $templateId,
        string $fieldId,
        Account $account,
    ): ?GuidelineFieldValue {
        return $this->findOneBy([
            'resourceType' => $resourceType,
            'resourceId' => $resourceId,
            'templateId' => $templateId,
            'fieldId' => $fieldId,
            'account' => $account,
        ]);
    }

    /**
     * The most-recently-updated non-empty value for a field across the WHOLE
     * account (any template, any other resource), for cross-investigation reuse.
     *
     * Account-scoped for tenant isolation: a value persisted in another account
     * is NEVER returned. Optionally excludes the current resource so a resource
     * does not cite itself.
     */
    public function findLatestForFieldAcrossAccount(
        string $fieldId,
        Account $account,
        ?Uuid $excludeResourceId = null,
    ): ?GuidelineFieldValue {
        return $this->findLatestValuesForFieldsAcrossAccount([$fieldId], $account, $excludeResourceId)[$fieldId] ?? null;
    }

    /**
     * Batch helper: the latest non-empty value per field id across the account.
     *
     * @param list<string> $fieldIds
     *
     * @return array<string, GuidelineFieldValue> keyed by field id
     */
    public function findLatestValuesForFieldsAcrossAccount(
        array $fieldIds,
        Account $account,
        ?Uuid $excludeResourceId = null,
    ): array {
        $fieldIds = array_values(array_unique(array_filter($fieldIds, static fn (string $id): bool => '' !== $id)));
        if ([] === $fieldIds) {
            return [];
        }

        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.fieldId IN (:fieldIds)')
            ->andWhere('IDENTITY(v.account) = :accountId')
            ->setParameter('fieldIds', $fieldIds)
            ->setParameter('accountId', $account->getId(), 'uuid')
            ->orderBy('v.updatedAt', 'DESC')
        ;

        if (null !== $excludeResourceId) {
            $qb->andWhere('v.resourceId != :excludeResourceId')
                ->setParameter('excludeResourceId', $excludeResourceId, 'uuid')
            ;
        }

        /** @var list<GuidelineFieldValue> $rows */
        $rows = $qb->getQuery()->getResult();

        $latest = [];
        foreach ($rows as $row) {
            // Rows are DESC by updatedAt, so the first NON-EMPTY row seen per field
            // id is the latest non-empty value. Empty/null fills (e.g. a field a
            // user later cleared) must NOT mask an older real value. The `value`
            // column is `json`, which has no portable SQL equality operator on
            // PostgreSQL, so the empty check is done here on the decoded value.
            if (isset($latest[$row->getFieldId()]) || !$this->isNonEmptyValue($row->getValue())) {
                continue;
            }
            $latest[$row->getFieldId()] = $row;
        }

        return $latest;
    }

    private function isNonEmptyValue(mixed $value): bool
    {
        if (null === $value) {
            return false;
        }
        if (\is_string($value)) {
            return '' !== trim($value);
        }
        if (\is_array($value)) {
            return [] !== $value;
        }

        return true;
    }
}
