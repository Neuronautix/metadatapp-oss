<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\GuidelineFieldValue;
use App\Guideline\ConformanceEngine;
use App\Guideline\GuidelineResourceLocator;
use App\Guideline\Report\CompletionReport;
use App\Guideline\Review\GuidelineReviewSummaryResolver;
use App\Guideline\TemplateLoader;
use App\Repository\GuidelineFieldValueRepository;
use App\Resource\GuidelineCompletionResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<GuidelineCompletionResource>
 */
final readonly class GuidelineCompletionProvider implements ProviderInterface
{
    public function __construct(
        private TemplateLoader $loader,
        private ConformanceEngine $engine,
        private GuidelineResourceLocator $locator,
        private GuidelineFieldValueRepository $fieldValues,
        private GuidelineReviewSummaryResolver $reviewSummary,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GuidelineCompletionResource
    {
        $id = (string) ($uriVariables['id'] ?? '');
        $templateId = (string) ($uriVariables['templateId'] ?? '');

        if (!$this->loader->has($templateId)) {
            throw new NotFoundHttpException(\sprintf('Guideline template "%s" not found.', $templateId));
        }
        $template = $this->loader->load($templateId);

        $uriTemplate = $operation instanceof HttpOperation ? (string) $operation->getUriTemplate() : '';
        $ctx = str_contains($uriTemplate, '/investigations/')
            ? $this->locator->locateInvestigation($id)
            : $this->locator->locateStudy($id);

        $entries = $this->engine->evaluate($template, $ctx['columns'], $ctx['metadata']);
        $report = new CompletionReport($template->id, $template->name, $entries, $ctx['metadata']);
        $data = $report->toArray();

        $account = $this->locator->currentUser()->getAccount();
        $reviewByField = [];
        foreach ($this->fieldValues->findForResource($ctx['resourceType'], $ctx['resourceId'], $templateId, $account) as $value) {
            $reviewByField[$value->getFieldId()] = $value;
        }

        $fields = array_map(static function (array $field) use ($reviewByField): array {
            $value = $reviewByField[$field['field_id']] ?? null;

            return [
                ...$field,
                'review_status' => $value?->getReviewStatus() ?? GuidelineFieldValue::REVIEW_DRAFT,
                'reviewed_by' => $value?->getReviewedBy()?->getEmail(),
                'reviewed_at' => $value?->getReviewedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }, $data['fields']);

        $resource = new GuidelineCompletionResource();
        $resource->id = \sprintf('%s:%s', $ctx['resourceId']->toRfc4122(), $templateId);
        $resource->resourceType = $ctx['resourceType'];
        $resource->templateId = $template->id;
        $resource->standard = $template->name;
        $resource->totals = $data['totals'];
        $resource->bySeverity = $data['by_severity'];
        $resource->bySection = $data['by_section'];
        $resource->fields = $fields;
        $resource->reportReview = $this->reviewSummary->summarize($ctx['resourceType'], $ctx['resourceId'], $templateId, $account);

        return $resource;
    }
}
