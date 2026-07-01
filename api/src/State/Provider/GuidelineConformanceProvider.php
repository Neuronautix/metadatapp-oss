<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Guideline\ConformanceEngine;
use App\Guideline\GuidelineResourceLocator;
use App\Guideline\Report\ConformanceReport;
use App\Guideline\TemplateLoader;
use App\Resource\GuidelineConformanceResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<GuidelineConformanceResource>
 */
final readonly class GuidelineConformanceProvider implements ProviderInterface
{
    public function __construct(
        private TemplateLoader $loader,
        private ConformanceEngine $engine,
        private GuidelineResourceLocator $locator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GuidelineConformanceResource
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
        $report = new ConformanceReport($template, $entries);
        $data = $report->toArray();

        $resource = new GuidelineConformanceResource();
        $resource->id = \sprintf('%s:%s', $ctx['resourceId']->toRfc4122(), $templateId);
        $resource->resourceType = $ctx['resourceType'];
        $resource->templateId = $template->id;
        $resource->standard = $template->name;
        $resource->version = $template->version;
        $resource->score = $data['score'];
        $resource->totals = $data['totals'];
        $resource->entries = $data['entries'];

        return $resource;
    }
}
