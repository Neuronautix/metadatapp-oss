<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Mock;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

class HttpClientMock extends MockHttpClient
{
    public function __construct(
    ) {
        parent::__construct($this->handleRequests(...));
    }

    private function handleRequests(string $method, string $url): MockResponse
    {
        $request = Request::create($url, $method);
        $path = $request->getPathInfo();
        $query = $request->query;

        $json = match (true) {
            str_ends_with($path, '/action/package_list') => $this->datasetsList(),
            str_ends_with($path, '/action/package_show') => $this->datasetShow($query->get('id')),
            str_ends_with($path, '/action/datastore_create') => $this->datastoreCreate(),
            str_ends_with($path, '/action/datastore_upsert') => $this->datastoreUpsert(),
            str_ends_with($path, '/action/datastore_delete') => $this->datastoreDelete(),
            str_starts_with($path, '/datastore/dump/') => $this->datastoreDump(),
            str_ends_with($path, '/action/resource_show') => $this->resourceShow($query->get('id')),
            str_ends_with($path, '/action/help_show') => $this->helpShow($query->get('name')),
            default => '{}',
        };

        return new MockResponse($json, ['http_code' => 200]);
    }

    private function datasetsList(): string
    {
        return <<<'JSON'
{
    "help": "https://www.fair3r.fr/api/3/action/help_show?name=package_list",
    "success": true,
    "result": [
        "f3r_bw_table",
        "behavioral-data-epm",
        "social-interaction-test",
        "glucose-tolerance-data",
        "cardiovascular-measurements",
        "memory-test-mwm"
    ]
}
JSON;
    }

    private function datasetShow(?string $id): string
    {
        // Return different datasets based on ID
        $datasets = [
            'f3r_bw_table' => [
                'id' => 'b6dddc9a-2327-434b-8381-95ee98f653bb',
                'name' => 'f3r_bw_table',
                'title' => 'Body Weight Measurements',
                'notes' => 'Longitudinal body weight data synchronized to Fair3R',
                'resource_id' => 'd407fd04-b390-4826-8bba-e0430a222d82',
            ],
            'behavioral-data-epm' => [
                'id' => 'a5cceb8a-1216-323a-7270-84dd87e542aa',
                'name' => 'behavioral-data-epm',
                'title' => 'Elevated Plus Maze Behavioral Data',
                'notes' => 'Anxiety behavior assessment from EPM testing',
                'resource_id' => 'c316ec03-a279-3715-7aa9-d0320a111c71',
            ],
            'social-interaction-test' => [
                'id' => 'f7ddfb9b-2327-434c-9492-a6ff09g764cc',
                'name' => 'social-interaction-test',
                'title' => 'Social Interaction Test Results',
                'notes' => 'Three-chamber social behavior test data',
                'resource_id' => 'e528ge15-c501-4937-9ccb-e1541b333e93',
            ],
            'glucose-tolerance-data' => [
                'id' => 'h8eegc0c-3438-545d-0603-b7gg10h875dd',
                'name' => 'glucose-tolerance-data',
                'title' => 'Glucose Tolerance Test Data',
                'notes' => 'GTT measurements for metabolic studies',
                'resource_id' => 'f639hf26-d612-5048-0ddc-f2652c444f04',
            ],
        ];

        $dataset = $datasets[$id] ?? $datasets['f3r_bw_table'];

        return <<<JSON
{
    "help": "https://www.fair3r.fr/api/3/action/help_show?name=package_show",
    "success": true,
    "result": {
        "author": "DHuzard",
        "author_email": "d.huzard@example.com",
        "creator_user_id": "96f14aaf-9cec-47b0-b8b8-9b8d200da6e1",
        "id": "{$dataset['id']}",
        "isopen": false,
        "license_id": "CC-BY-4.0",
        "license_title": "Creative Commons Attribution 4.0",
        "maintainer": "Research Team",
        "maintainer_email": "research@example.com",
        "metadata_created": "2024-08-20T13:01:50.007469",
        "metadata_modified": "2024-11-01T16:00:15.831206",
        "name": "{$dataset['name']}",
        "notes": "{$dataset['notes']}",
        "num_resources": 1,
        "num_tags": 3,
        "organization": {
            "id": "9c5e11b2-2d3f-49b5-8440-4565eb79b15f",
            "name": "example_research_lab",
            "title": "Example Research Lab",
            "type": "organization",
            "description": "Neuroscience and behavioral research organization",
            "image_url": "",
            "created": "2024-04-29T11:59:48.434200",
            "is_organization": true,
            "approval_status": "approved",
            "state": "active"
        },
        "owner_org": "9c5e11b2-2d3f-49b5-8440-4565eb79b15f",
        "private": true,
        "state": "active",
        "title": "{$dataset['title']}",
        "type": "dataset",
        "url": "",
        "version": "1.0",
        "resources": [
            {
                "cache_last_updated": null,
                "cache_url": null,
                "created": "2024-08-31T16:00:15.662720",
                "datastore_active": true,
                "datastore_contains_all_records_of_source_file": true,
                "description": "{$dataset['notes']}",
                "format": "JSON",
                "hash": "",
                "id": "{$dataset['resource_id']}",
                "last_modified": "2024-11-01T14:30:00.000000",
                "metadata_modified": "2024-11-01T16:00:15.835211",
                "mimetype": "application/json",
                "mimetype_inner": null,
                "name": "{$dataset['title']}",
                "package_id": "{$dataset['id']}",
                "position": 0,
                "resource_type": "datastore",
                "size": 15432,
                "state": "active",
                "url": "https://www.fair3r.fr/datastore/dump/{$dataset['resource_id']}",
                "url_type": "datastore"
            }
        ],
        "tags": [
            {"name": "neuroscience"},
            {"name": "mouse-model"},
            {"name": "experimental-data"}
        ],
        "extras": [
            {"key": "study_type", "value": "longitudinal"},
            {"key": "species", "value": "Mus musculus"}
        ],
        "groups": [],
        "relationships_as_subject": [],
        "relationships_as_object": [],
        "doi": "10.83249/{$dataset['id']}",
        "doi_status": true,
        "domain": "https://www.fair3r.fr",
        "doi_date_published": "2024-11-01",
        "doi_publisher": "Example Research Lab"
    }
}
JSON;
    }

    private function datastoreCreate(): string
    {
        return <<<'JSON'
{
    "help": "https://www.fair3r.fr/api/3/action/help_show?name=datastore_create",
    "success": true,
    "result": {
        "fields": [
            {
                "id": "date",
                "type": "timestamp"
            },
            {
                "id": "subject",
                "type": "text"
            },
            {
                "id": "weight",
                "type": "float"
            },
            {
                "id": "anotherCleverMeta",
                "type": "float"
            }
        ],
        "resource": {
            "package_id": "f3r_bw_table",
            "name": "WM SM F3R OK",
            "description": "This experiment aims to disorder.",
            "url": "_datastore_only_resource",
            "mimetype": null
        },
        "resource_id": "d6a08190-3058-4ec7-8bd0-d0e93975d458",
        "method": "insert"
    }
}
JSON;
    }

    private function datastoreUpsert(): string
    {
        // Generate realistic upsert response with varied data
        $baseDate = new \DateTime('2024-10-15');
        $records = [];

        for ($i = 0; $i < 10; ++$i) {
            $date = clone $baseDate;
            $date->add(new \DateInterval("P{$i}D"));
            $dateStr = $date->format('Y-m-d');

            $subjectNum = ($i % 3) + 1;
            // Use deterministic variance based on index for consistent test results
            $variance = ($i % 5) / 5.0; // 0, 0.2, 0.4, 0.6, 0.8, 0, ...
            $weight = 22.0 + ($i * 0.5) + $variance;

            $record = [
                'subject' => "https://www.mapp.ntx/subjects/subject-{$subjectNum}",
                'weight' => round($weight, 1),
                'date' => $dateStr,
            ];

            // Add some additional metadata for variety
            if (0 === $i % 2) {
                $record['notes'] = 'Regular measurement';
            }
            if (0 === $i % 3) {
                $record['measuredBy'] = 'technician_' . (($i % 3) + 1);
            }

            $records[] = $record;
        }

        $recordsJson = json_encode($records, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);

        return <<<JSON
{
  "help": "https://www.fair3r.fr/api/3/action/help_show?name=datastore_upsert",
  "success": true,
  "result": {
    "__extras": {
      "resource": {
        "package_id": "f3r_bw_table",
        "name": "Body Weight Measurements",
        "description": "Longitudinal body weight data synchronized from Metadatapp"
      }
    },
    "method": "upsert",
    "resource_id": "d6a08190-3058-4ec7-8bd0-d0e93975d458",
    "records": {$recordsJson}
  }
}
JSON;
    }

    private function datastoreDelete(): string
    {
        return <<<'JSON'
        {
          "help": "https://www.fair3r.fr/api/3/action/help_show?name=datastore_delete",
          "success": true,
          "result": {
            "records_deleted": true
          }
        }
        JSON;
    }

    private function datastoreDump(): string
    {
        // Generate realistic body weight data
        $baseDate = new \DateTime('2024-10-01');
        $records = [];

        // Generate data for 5 subjects over 8 weeks
        $subjects = [
            ['id' => 1, 'baseWeight' => 22.5],
            ['id' => 2, 'baseWeight' => 21.8],
            ['id' => 3, 'baseWeight' => 23.2],
            ['id' => 4, 'baseWeight' => 22.1],
            ['id' => 5, 'baseWeight' => 24.0],
        ];

        for ($week = 0; $week < 8; ++$week) {
            $measureDate = clone $baseDate;
            $measureDate->add(new \DateInterval("P{$week}W"));
            $dateStr = $measureDate->format('Y-m-d\TH:i:s');

            foreach ($subjects as $subjectIndex => $subject) {
                // Simulate weight gain over time with deterministic variance
                // Use week and subject index for consistent but varied results
                $variance = (($week + $subjectIndex) % 10 - 5) / 10.0; // Range: -0.5 to 0.4
                $weight = $subject['baseWeight'] + ($week * 0.8) + $variance;
                $records[] = \sprintf(
                    '[%d,%s,"%s"]',
                    \count($records) + 1,
                    number_format($weight, 1, '.', ''),
                    $dateStr
                );
            }
        }

        $recordsJson = implode(",\n    ", $records);

        return <<<JSON
{
  "fields": [
    {"id":"_id","type":"int4"},
    {"id":"weight","type":"float8"},
    {"id":"date","type":"timestamp"},
    {"id":"subject_id","type":"text"},
    {"id":"experiment_code","type":"text"}
  ],
  "records": [
    {$recordsJson}
  ]
}
JSON;
    }

    private function resourceShow(?string $id): string
    {
        return <<<'JSON'
{
    "help": "https://www.fair3r.fr/api/3/action/help_show?name=resource_show",
    "success": true,
    "result": {
        "cache_last_updated": null,
        "cache_url": null,
        "created": "2025-05-31T16:00:15.662720",
        "datastore_active": true,
        "datastore_contains_all_records_of_source_file": false,
        "description": null,
        "format": "",
        "hash": "",
        "id": "d407fd04-b390-4826-8bba-e0430a222d82",
        "last_modified": null,
        "metadata_modified": "2025-05-31T16:00:15.835211",
        "mimetype": null,
        "mimetype_inner": null,
        "name": null,
        "package_id": "b6dddc9a-2327-434b-8381-95ee98f653bb",
        "position": 0,
        "resource_type": null,
        "size": null,
        "state": "active",
        "url": "https://www.fair3r.fr/datastore/dump/d407fd04-b390-4826-8bba-e0430a222d82",
        "url_type": "datastore"
    }
}
JSON;
    }

    private function helpShow(?string $name): string
    {
        return <<<'JSON'
{
    "help": "https://www.fair3r.fr/api/3/action/help_show?name=help_show",
    "success": true,
    "result": "Return the help string for a particular API action.      :param name: Action function name (eg `user_create`, `package_search`)     :type name: string     :returns: The help string for the action function, or None if the function               does not have a docstring.     :rtype: string      :raises: :class:`ckan.logic.NotFound`: if the action function doesn't exist      "
}
JSON;
    }
}
