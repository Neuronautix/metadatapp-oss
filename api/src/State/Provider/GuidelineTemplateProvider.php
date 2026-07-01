<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Guideline\Schema\GuidelineTemplate;
use App\Guideline\Schema\RequiredColumn;
use App\Guideline\Schema\RequiredMetadata;
use App\Guideline\TemplateLoader;
use App\Resource\GuidelineTemplateResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides the guideline template catalogue (collection) and a single resolved
 * template (item). Read-only; secured at the resource level to ROLE_USER.
 *
 * @implements ProviderInterface<GuidelineTemplateResource>
 */
final readonly class GuidelineTemplateProvider implements ProviderInterface
{
    public function __construct(
        private TemplateLoader $loader,
    ) {
    }

    /**
     * @return list<GuidelineTemplateResource>|GuidelineTemplateResource
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|GuidelineTemplateResource
    {
        if ($operation instanceof CollectionOperationInterface) {
            return array_map(
                fn (GuidelineTemplate $t): GuidelineTemplateResource => $this->toResource($t, false),
                $this->loader->loadAll(),
            );
        }

        $templateId = (string) ($uriVariables['templateId'] ?? '');
        if (!$this->loader->has($templateId)) {
            throw new NotFoundHttpException(\sprintf('Guideline template "%s" not found.', $templateId));
        }

        return $this->toResource($this->loader->load($templateId), true);
    }

    private function toResource(GuidelineTemplate $template, bool $withFields): GuidelineTemplateResource
    {
        $resource = new GuidelineTemplateResource();
        $resource->templateId = $template->id;
        $resource->name = $template->name;
        $resource->version = $template->version;
        $resource->description = $template->description;
        $resource->identifier = $template->identifier;
        $resource->conformsTo = $template->conformsTo;
        $resource->requiredColumnCount = \count($template->requiredColumns);
        $resource->optionalColumnCount = \count($template->optionalColumns);
        $resource->requiredMetadataCount = \count($template->requiredMetadata);
        $resource->fairBonus = $template->fairBonus;

        if ($withFields) {
            $resource->requiredColumns = array_map([$this, 'columnToArray'], $template->requiredColumns);
            $resource->optionalColumns = array_map([$this, 'columnToArray'], $template->optionalColumns);
            $resource->requiredMetadata = array_map([$this, 'metadataToArray'], $template->requiredMetadata);
        }

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    private function columnToArray(RequiredColumn $col): array
    {
        return [
            'id' => $col->id,
            'name_patterns' => $col->namePatterns,
            'semantic_type' => $col->semanticType,
            'role' => $col->role,
            'arrive_section' => $col->arriveSection,
            'unit_required' => $col->unitRequired,
            'unit_column' => $col->unitColumn,
            'required' => $col->required,
            'severity' => $col->severity,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataToArray(RequiredMetadata $meta): array
    {
        return [
            'id' => $meta->id,
            'arrive_section' => $meta->arriveSection,
            'prepare_section' => $meta->prepareSection,
            'eqipd_section' => $meta->eqipdSection,
            'guidance' => $meta->guidance,
            'prompt' => $meta->prompt,
            'crosswalk' => $meta->crosswalk,
            'severity' => $meta->severity,
        ];
    }
}
