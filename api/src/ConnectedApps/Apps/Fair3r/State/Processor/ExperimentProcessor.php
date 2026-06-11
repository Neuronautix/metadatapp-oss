<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ConnectedApps\Apps\Fair3r\Fair3rService;
use App\ConnectedApps\ConnectedAppServiceFactory;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AppCode;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<Experiment, Experiment>
 */
final readonly class ExperimentProcessor implements ProcessorInterface
{
    /**
     * @var ProcessorInterface<Experiment, Experiment>
     */
    private readonly ProcessorInterface $persistProcessor;

    /**
     * @param ProcessorInterface<Experiment, Experiment> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        ProcessorInterface $persistProcessor,
        private Security $security,
        private ConnectedAppServiceFactory $connectedAppServiceFactory,
    ) {
        $this->persistProcessor = $persistProcessor;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @phpstan-ignore-next-line */
        if (!$data instanceof Experiment) {
            return $data;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $data;
        }

        $app = $user->getConnectedAppByCode(AppCode::Fair3r);
        if (!$app instanceof ConnectedApp) {
            return $data;
        }

        $project = $data->getProject();
        if (!$project instanceof Project) {
            return $data;
        }

        if (null === $project->getFair3rExternalId()) {
            return $data;
        }

        $faire3rService = $this->connectedAppServiceFactory->createService(AppCode::Fair3r);
        if (!$faire3rService instanceof Fair3rService) {
            throw new \RuntimeException('Fair3r service not found for fair3r connected App .');
        }

        try {
            $faire3rService->syncExperiment($app, $project);
        } catch (\Exception $e) {
            throw new \RuntimeException('Error syncing Project to Fair3r: ' . $e->getMessage(), 0, $e);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
