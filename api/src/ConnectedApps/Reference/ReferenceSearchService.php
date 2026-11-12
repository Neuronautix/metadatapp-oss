<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference;

use App\ConnectedApps\Reference\Semantic\QueryExpansionService;
use App\Entity\ConnectedApp;
use App\Entity\User;
use App\Enum\AppCode;
use App\Repository\ConnectedAppRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Fans one query out to every active connected app (optionally restricted to a
 * selected subset) via its {@see ReferenceSearchAdapterInterface}, isolating each
 * adapter so a single slow/broken upstream can never fail the whole search.
 *
 * When the semantic-discovery flag is on it additionally expands the query with
 * ontology synonyms ({@see QueryExpansionService}) and federates each term. The flag
 * is OFF by default, in which case search is byte-for-byte identical to before:
 * exactly one term (the original query) is federated.
 */
final readonly class ReferenceSearchService
{
    private const int MAX_LIMIT = 50;

    /**
     * @param iterable<ReferenceSearchAdapterInterface> $adapters
     */
    public function __construct(
        private ConnectedAppRepository $connectedAppRepository,
        private Security $security,
        private LoggerInterface $logger,
        #[AutowireIterator('app.reference_search_adapter')]
        private iterable $adapters,
        private QueryExpansionService $queryExpansion,
        /** Gate for ontology query expansion; false = unchanged literal search. */
        private bool $semanticSearchEnabled = false,
    ) {
    }

    /**
     * @param list<string> $appCodes restrict to these AppCode values; empty = all active
     *
     * @return list<ReferenceResult>
     */
    public function search(string $query, array $appCodes = [], int $limit = 20): array
    {
        $query = trim($query);
        $user = $this->security->getUser();
        if ('' === $query || !$user instanceof User) {
            return [];
        }

        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $wanted = array_map(static fn (string $code): string => mb_strtolower(trim($code)), $appCodes);

        // The account's persisted+active apps take precedence (custom URL/token),
        // but the public reference databases are always searchable without setup.
        $activeByCode = [];
        foreach ($this->connectedAppRepository->findAllActiveForUser($user) as $connectedApp) {
            $activeByCode[$connectedApp->getCode()->value] = $connectedApp;
        }

        $publicDefaults = self::publicDefaults();
        $codes = [] !== $wanted
            ? $wanted
            : array_values(array_unique([...array_keys($activeByCode), ...array_keys($publicDefaults)]));

        // Default (flag off): the single literal query, unchanged. When the semantic
        // flag is on, additionally federate ontology-synonym terms.
        $terms = $this->semanticSearchEnabled ? $this->queryExpansion->expand($query) : [$query];

        // De-dupe across terms by federated id so an expansion can only widen recall,
        // never duplicate a row. First occurrence wins (original query is first).
        $results = [];
        $seenIds = [];
        foreach ($codes as $codeValue) {
            $code = AppCode::tryFrom($codeValue);
            if (null === $code) {
                continue;
            }

            $adapter = $this->adapterFor($code);
            if (null === $adapter) {
                continue;
            }

            $connectedApp = $activeByCode[$codeValue] ?? null;
            if (null === $connectedApp) {
                if (!isset($publicDefaults[$codeValue])) {
                    continue; // not active for this account and not a public default
                }
                $connectedApp = $this->defaultPublicApp($code, $publicDefaults[$codeValue]);
            }

            foreach ($terms as $term) {
                try {
                    foreach ($adapter->search($connectedApp, $term, $limit) as $result) {
                        if (isset($seenIds[$result->id])) {
                            continue;
                        }
                        $seenIds[$result->id] = true;
                        $results[] = $result;
                    }
                } catch (\Throwable $e) {
                    // Partial results are expected: log and keep federating.
                    $this->logger->warning('Reference search adapter failed', [
                        'app' => $code->value,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Keyless public reference databases that are always searchable, even when
     * the account has not explicitly added them. Value = display name; the base
     * URL is left to each client's own public default.
     *
     * @return array<string, string>
     */
    private static function publicDefaults(): array
    {
        return [
            AppCode::JaxPhenome->value => 'JAX Phenome (MPD)',
            AppCode::Impc->value => 'IMPReSS (IMPC)',
            AppCode::NihCde->value => 'NIH CDE Repository',
            AppCode::PreclinicalTrials->value => 'PreclinicalTrials.eu',
            AppCode::Mnms->value => 'MNMS',
            AppCode::GuidelinesHub->value => 'Guidelines Hub',
        ];
    }

    private function defaultPublicApp(AppCode $code, string $name): ConnectedApp
    {
        $app = new ConnectedApp();
        $app->setCode($code);
        $app->setName($name);

        return $app;
    }

    private function adapterFor(AppCode $code): ?ReferenceSearchAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($code)) {
                return $adapter;
            }
        }

        return null;
    }
}
