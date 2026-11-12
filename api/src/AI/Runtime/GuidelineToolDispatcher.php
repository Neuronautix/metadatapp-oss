<?php

declare(strict_types=1);

namespace App\AI\Runtime;

use App\AI\Mcp\McpToolCallResult;
use App\Entity\Experiment;
use App\Entity\GuidelineFieldValue;
use App\Entity\Project;
use App\Entity\User;
use App\Guideline\Evidence\EvidenceBundle;
use App\Guideline\Evidence\EvidenceItem;
use App\Guideline\Evidence\GuidelineEvidenceRetriever;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

/**
 * Read-only {@see ToolDispatcherInterface} for the guideline-evidence agentic
 * tool `read_guideline_evidence`. It hands the existing agentic loop the SAME
 * deterministic, locally-computed evidence the HTTP guideline endpoints expose,
 * account-scoped via {@see Security} — it NEVER calls a ConnectedApps client.
 */
final readonly class GuidelineToolDispatcher implements ToolDispatcherInterface
{
    private const string TOOL_NAME = 'read_guideline_evidence';
    private const string TOOL_CALL_FAILED_MESSAGE = 'Tool call failed.';

    public function __construct(
        private GuidelineEvidenceRetriever $retriever,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private LoggerInterface $logger,
    ) {
    }

    public function supports(string $toolName): bool
    {
        return self::TOOL_NAME === $toolName;
    }

    public function dispatch(string $toolName, array $arguments): McpToolCallResult
    {
        $start = microtime(true);

        try {
            if (self::TOOL_NAME !== $toolName) {
                throw new \InvalidArgumentException(\sprintf('No dispatcher handler for tool "%s".', $toolName));
            }

            return new McpToolCallResult(
                toolName: $toolName,
                success: true,
                data: $this->readEvidence($arguments),
                callDurationMs: $this->elapsedMs($start),
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Guideline evidence tool dispatch failed.', [
                'tool' => $toolName,
                'exception_class' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return new McpToolCallResult(
                toolName: $toolName,
                success: false,
                error: self::TOOL_CALL_FAILED_MESSAGE,
                callDurationMs: $this->elapsedMs($start),
            );
        }
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function readEvidence(array $arguments): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return ['available' => false, 'evidence' => []];
        }
        $account = $user->getAccount();

        $resourceType = strtolower(trim((string) ($arguments['resourceType'] ?? '')));
        $resourceIdRaw = trim((string) ($arguments['resourceId'] ?? ''));
        $fieldId = isset($arguments['fieldId']) && '' !== trim((string) $arguments['fieldId'])
            ? trim((string) $arguments['fieldId'])
            : null;

        try {
            $resourceId = Uuid::fromString($resourceIdRaw);
        } catch (\InvalidArgumentException) {
            return ['available' => false, 'evidence' => []];
        }

        $bundle = $this->resolveBundle($resourceType, $resourceId, $account->getId());
        if (null === $bundle) {
            return ['available' => false, 'evidence' => []];
        }

        $items = null !== $fieldId ? $this->itemsForFieldId($bundle, $fieldId) : $bundle->all();

        return [
            'available' => true,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId->toRfc4122(),
            'field_id' => $fieldId,
            'evidence' => array_map(static fn (EvidenceItem $i): array => $i->toPromptArray(), $items),
        ];
    }

    /**
     * Account-scoped resolution: the resource is loaded ONLY when it belongs to
     * the current account (tenant isolation; a cross-account id resolves to null).
     */
    private function resolveBundle(string $resourceType, Uuid $resourceId, ?Uuid $accountId): ?EvidenceBundle
    {
        if (null === $accountId) {
            return null;
        }

        if (GuidelineFieldValue::RESOURCE_INVESTIGATION === $resourceType) {
            /** @var Project|null $project */
            $project = $this->entityManager->getRepository(Project::class)->find($resourceId);
            if (null === $project || !$this->sameAccount($project->getAccount()->getId(), $accountId)) {
                return null;
            }

            return $this->retriever->retrieveForInvestigation($project);
        }

        if (GuidelineFieldValue::RESOURCE_STUDY === $resourceType) {
            /** @var Experiment|null $experiment */
            $experiment = $this->entityManager->getRepository(Experiment::class)->find($resourceId);
            if (null === $experiment || !$this->sameAccount($experiment->getAccount()->getId(), $accountId)) {
                return null;
            }

            return $this->retriever->retrieveForStudy($experiment);
        }

        return null;
    }

    private function sameAccount(?Uuid $resourceAccountId, Uuid $accountId): bool
    {
        return null !== $resourceAccountId && $resourceAccountId->equals($accountId);
    }

    /**
     * Items mapped to the given field id, plus general (field-less) facts.
     *
     * @return list<EvidenceItem>
     */
    private function itemsForFieldId(EvidenceBundle $bundle, string $fieldId): array
    {
        $matched = [];
        $general = [];
        foreach ($bundle->all() as $item) {
            if ($item->fieldId === $fieldId) {
                $matched[] = $item;
            } elseif (null === $item->fieldId) {
                $general[] = $item;
            }
        }

        return array_merge($matched, $general);
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
