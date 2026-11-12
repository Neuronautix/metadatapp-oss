<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Mock;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Mock HTTP client for ElabFTW API, used in dev and test environments.
 * Simulates eLabFTW REST API v2 responses without a real connection.
 */
class ElabftwHttpClientMock extends MockHttpClient
{
    /** @var array<int, array<string, mixed>> */
    private array $createdExperiments = [];

    /** @var array<int, array<string, mixed>> */
    private array $createdItems = [];

    private int $nextExperimentId = 100;
    private int $nextItemId = 200;

    public function __construct()
    {
        parent::__construct($this->handleRequests(...), 'https://elabftw.example.invalid/api/v2/');
    }

    private function handleRequests(string $method, string $url): MockResponse
    {
        $request = Request::create($url, $method);
        $path = $request->getPathInfo();

        return match (true) {
            'POST' === $method && str_ends_with($path, '/experiments') => $this->createExperiment($url),
            'GET' === $method && (bool) preg_match('#/experiments/(\d+)$#', $path, $m) => $this->getExperiment((int) $m[1]),
            'PATCH' === $method && (bool) preg_match('#/experiments/(\d+)$#', $path, $m) => $this->patchExperiment((int) $m[1]),
            'GET' === $method && str_contains($path, '/experiments_templates') => $this->listExperimentsTemplates(),
            'GET' === $method && str_ends_with($path, '/experiments') => $this->listExperiments(),
            'POST' === $method && str_ends_with($path, '/items') => $this->createItem($url),
            'GET' === $method && (bool) preg_match('#/items/(\d+)$#', $path, $m) => $this->getItem((int) $m[1]),
            'PATCH' === $method && (bool) preg_match('#/items/(\d+)$#', $path, $m) => $this->patchItem((int) $m[1]),
            'GET' === $method && str_contains($path, '/items_types') => $this->listItemsTypes(),
            'GET' === $method && str_ends_with($path, '/items') => $this->listItems(),
            'GET' === $method && str_ends_with($path, '/users') => $this->listUsers(),
            'GET' === $method && (bool) preg_match('#/users/(\d+|me)$#', $path, $m) => $this->getUser($m[1]),
            default => throw new \RuntimeException(\sprintf('Unhandled ElabFTW mock request: %s %s', $method, $url)),
        };
    }

    private function listExperimentsTemplates(): MockResponse
    {
        return new MockResponse(
            json_encode([], \JSON_THROW_ON_ERROR),
            ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']],
        );
    }

    private function listItemsTypes(): MockResponse
    {
        return new MockResponse(
            json_encode([], \JSON_THROW_ON_ERROR),
            ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']],
        );
    }

    private function listExperiments(): MockResponse
    {
        $experiments = $this->defaultExperiments();
        foreach ($this->createdExperiments as $exp) {
            $experiments[] = $exp;
        }

        return new MockResponse(
            json_encode($experiments, \JSON_THROW_ON_ERROR),
            ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']],
        );
    }

    private function getExperiment(int $id): MockResponse
    {
        if (isset($this->createdExperiments[$id])) {
            return new MockResponse(
                json_encode($this->createdExperiments[$id], \JSON_THROW_ON_ERROR),
                ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']],
            );
        }

        foreach ($this->defaultExperiments() as $exp) {
            if ($exp['id'] === $id) {
                return new MockResponse(
                    json_encode($exp, \JSON_THROW_ON_ERROR),
                    ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']],
                );
            }
        }

        return new MockResponse('', ['http_code' => 404]);
    }

    private function createExperiment(string $url): MockResponse
    {
        $id = ++$this->nextExperimentId;
        $experiment = $this->makeExperiment($id, 'New Experiment ' . $id, '<p>Created via MAPP sync.</p>');
        $this->createdExperiments[$id] = $experiment;
        $locationBase = (string) preg_replace('#/experiments.*$#', '', $url);

        return new MockResponse(
            '',
            [
                'http_code' => 201,
                'response_headers' => [
                    'Location' => $locationBase . '/experiments/' . $id,
                    'Content-Type' => 'application/json',
                ],
            ],
        );
    }

    private function patchExperiment(int $id): MockResponse
    {
        $experiment = $this->createdExperiments[$id]
            ?? $this->findDefaultExperiment($id)
            ?? $this->makeExperiment($id, 'Unknown', '');
        $experiment['date_modified'] = (new \DateTime())->format('Y-m-d H:i:s');

        return new MockResponse(
            json_encode($experiment, \JSON_THROW_ON_ERROR),
            ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']],
        );
    }

    private function listItems(): MockResponse
    {
        $items = $this->defaultItems();
        foreach ($this->createdItems as $item) {
            $items[] = $item;
        }

        return new MockResponse(
            json_encode($items, \JSON_THROW_ON_ERROR),
            ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']],
        );
    }

    private function getItem(int $id): MockResponse
    {
        if (isset($this->createdItems[$id])) {
            return new MockResponse(
                json_encode($this->createdItems[$id], \JSON_THROW_ON_ERROR),
                ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']],
            );
        }

        foreach ($this->defaultItems() as $item) {
            if ($item['id'] === $id) {
                return new MockResponse(
                    json_encode($item, \JSON_THROW_ON_ERROR),
                    ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']],
                );
            }
        }

        return new MockResponse('', ['http_code' => 404]);
    }

    private function createItem(string $url): MockResponse
    {
        $id = ++$this->nextItemId;
        $item = $this->makeItem($id, 'New Resource ' . $id);
        $this->createdItems[$id] = $item;
        $locationBase = (string) preg_replace('#/items.*$#', '', $url);

        return new MockResponse(
            '',
            [
                'http_code' => 201,
                'response_headers' => [
                    'Location' => $locationBase . '/items/' . $id,
                    'Content-Type' => 'application/json',
                ],
            ],
        );
    }

    private function patchItem(int $id): MockResponse
    {
        $item = $this->createdItems[$id]
            ?? $this->findDefaultItem($id)
            ?? $this->makeItem($id, 'Unknown');
        $item['date_modified'] = (new \DateTime())->format('Y-m-d H:i:s');

        return new MockResponse(
            json_encode($item, \JSON_THROW_ON_ERROR),
            ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']],
        );
    }

    private function listUsers(): MockResponse
    {
        return new MockResponse(
            json_encode($this->defaultUsers(), \JSON_THROW_ON_ERROR),
            ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']],
        );
    }

    private function getUser(string $id): MockResponse
    {
        foreach ($this->defaultUsers() as $user) {
            if ((string) $user['userid'] === $id || 'me' === $id) {
                return new MockResponse(
                    json_encode($user, \JSON_THROW_ON_ERROR),
                    ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']],
                );
            }
        }

        return new MockResponse('', ['http_code' => 404]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultExperiments(): array
    {
        return [
            $this->makeExperiment(
                1,
                'Open Field Test - Cohort A',
                '<h2>Protocol</h2><p>Mice placed in 50×50 cm arena for 30 min. Subjects: MOUSE-0001, MOUSE-0002, MOUSE-0003</p>',
                ['open-field', 'behavior', 'anxiety'],
                1,
            ),
            $this->makeExperiment(
                2,
                'Elevated Plus Maze - Week 4',
                '<h2>Protocol</h2><p>Standard EPM protocol, 5 min session. Subjects: MOUSE-0004, MOUSE-0005</p>',
                ['epm', 'anxiety', 'week-4'],
                1,
            ),
            $this->makeExperiment(
                3,
                'Morris Water Maze - Training',
                '<h2>Training</h2><p>Hidden platform spatial memory task. Subjects: MOUSE-0001, MOUSE-0003, MOUSE-0005</p>',
                ['mwm', 'memory', 'spatial'],
                2,
            ),
            $this->makeExperiment(
                4,
                'Surgery - Stereotaxic Implant',
                '<h2>Surgery</h2><p>Ketamine/xylazine 0.1 mL IP. Subjects: MOUSE-0006, MOUSE-0007</p>',
                ['surgery', 'implant'],
                3,
            ),
        ];
    }

    /**
     * @param string[] $tags
     *
     * @return array<string, mixed>
     */
    private function makeExperiment(int $id, string $title, string $body, array $tags = [], int $category = 1): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'status' => 1,
            'category' => $category,
            'tags' => $tags,
            'date' => '2025-03-01',
            'date_modified' => '2025-03-01 10:00:00',
            'userid' => 1,
            'locked' => 0,
            'metadata' => null,
            'next_step' => '',
            'rating' => 0,
            'elabid' => \sprintf('%s-%s%d', date('Ymd'), substr(md5((string) $id), 0, 8), $id),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDefaultExperiment(int $id): ?array
    {
        foreach ($this->defaultExperiments() as $exp) {
            if ($exp['id'] === $id) {
                return $exp;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultItems(): array
    {
        return [
            $this->makeItem(1, 'AnimalCohort COH-001', 1, '{"extra_fields":{"ID":{"type":"text","value":"COH-001"},"Sex":{"type":"select","value":"M"},"Strain":{"type":"text","value":"C57BL/6"},"Genotype":{"type":"select","value":"WT"}}}'),
            $this->makeItem(2, 'AnimalCohort COH-002', 1, '{"extra_fields":{"ID":{"type":"text","value":"COH-002"},"Sex":{"type":"select","value":"F"},"Strain":{"type":"text","value":"C57BL/6J"},"Genotype":{"type":"select","value":"KO"}}}'),
            $this->makeItem(3, 'Antibody - Anti-GFAP', 2, '{"extra_fields":{"catalog_number":{"type":"text","value":"AB5541"},"supplier":{"type":"text","value":"Millipore"},"dilution":{"type":"text","value":"1:500"}}}'),
            $this->makeItem(4, 'Open Field Behavioral Rig', 3, null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeItem(int $id, string $title, int $categoryId = 1, ?string $metadata = null): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'body' => '<p>Resource entry managed by MAPP.</p>',
            'category_id' => $categoryId,
            'metadata' => $metadata,
            'date' => '2025-01-15',
            'date_modified' => '2025-03-01 09:00:00',
            'locked' => 0,
            'userid' => 1,
            'rating' => 0,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDefaultItem(int $id): ?array
    {
        foreach ($this->defaultItems() as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultUsers(): array
    {
        return [
            [
                'userid' => 1,
                'firstname' => 'Damien',
                'lastname' => 'Huzard',
                'initials' => 'DH',
                'email' => 'damien.huzard@example.com',
                'validated' => 1,
                'valid_until' => null,
                'archived' => 0,
                'last_login' => '2025-03-01 08:30:00',
                'fullname' => 'Damien Huzard',
                'orcid' => null,
                'orgid' => null,
                'auth_service' => null,
                'is_sysadmin' => 1,
                'entrypoint' => 1,
                'sig_pubkey' => null,
                'teams' => [
                    ['id' => 1, 'name' => 'Neuronautix Lab', 'usergroup' => 1, 'isOwner' => 1],
                ],
            ],
            [
                'userid' => 2,
                'firstname' => 'Alice',
                'lastname' => 'Dupont',
                'initials' => 'AD',
                'email' => 'alice.dupont@example.com',
                'validated' => 1,
                'valid_until' => null,
                'archived' => 0,
                'last_login' => '2025-02-28 14:00:00',
                'fullname' => 'Alice Dupont',
                'orcid' => null,
                'orgid' => null,
                'auth_service' => null,
                'is_sysadmin' => 0,
                'entrypoint' => 1,
                'sig_pubkey' => null,
                'teams' => [
                    ['id' => 1, 'name' => 'Neuronautix Lab', 'usergroup' => 2, 'isOwner' => 0],
                ],
            ],
        ];
    }
}
