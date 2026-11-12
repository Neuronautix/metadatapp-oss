<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Curation\Application\CreateImportSession;
use App\Entity\ImportSession;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class SessionUploadProcessor implements ProcessorInterface
{
    public function __construct(
        private CreateImportSession $createSession,
        private \App\Curation\Application\ProfileImportSession $profileSession,
        private \App\Curation\Application\GenerateSchemaMappingProposals $generateSchema,
        private \App\Curation\Application\GenerateNormalizationProposals $generateNormalization,
        private \App\Curation\IO\CsvParser $csvParser,
        private RequestStack $requestStack,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ImportSession
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user) {
            throw new BadRequestHttpException('User required.');
        }

        $account = $user->getAccount();

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new BadRequestHttpException('No request context.');
        }
        $file = $request->files->get('file');

        if (!$file) {
            throw new BadRequestHttpException('File required.');
        }

        /** @var string|false $pathName */
        $pathName = $file->getPathname();
        if (false === $pathName) {
            throw new BadRequestHttpException('Invalid file path.');
        }
        $fingerprint = (string) md5_file($pathName);

        $session = $this->createSession->execute($fingerprint, $account, $user);

        // Parse and Profile immediately for streamlined agent experience
        $parsed = $this->csvParser->parse($pathName);
        $columns = array_map(static fn ($h) => ['header' => $h], $parsed['headers']);

        $this->profileSession->execute($session, $columns, $parsed['rows']);
        $this->generateSchema->execute($session);
        $this->generateNormalization->execute($session);

        return $session;
    }
}
