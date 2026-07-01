<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Mock;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class HttpClientMock extends MockHttpClient
{
    public function __construct()
    {
        $callback = $this->handleRequests(...);
        parent::__construct($callback, 'https://api.example.com/');
    }

    protected function getWeight(): float
    {
        // Use deterministic weight calculation based on current second for variety
        // but still predictable within the same second
        $second = max(1, (int) (new \DateTime())->format('s'));

        return 20.0 + ($second % 10) * 0.5; // Range: 20.0 to 24.5
    }

    private function login(): string
    {
        return <<<'JSON'
         {
                "messageCode": "200",
                "messageDesc": "SUCCESS",
                "respObj": "TOOTOTOTIKENLOGINFAKEREAL"
        }
        JSON;
    }

    private function handleRequests(string $method, string $uri): MockResponse
    {
        // Parse the URI to extract path and query parameters reliably
        $parts = parse_url($uri);
        $path = $parts['path'] ?? $uri;
        $queryParams = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $queryParams);
        }

        $studyName = $queryParams['studyName'] ?? null;
        $json = match (true) {
            str_ends_with($uri, 'login') => $this->login(),
            str_contains($uri, 'external/v1.3/animal') => str_contains($uri, 'animalSID') ? $this->animalBySid() : $this->animalsList(),
            str_contains($uri, 'external/v1.2/cage') => str_contains($uri, 'cageIds') ? $this->cageById() : $this->cagesList(),
            str_contains($uri, 'external/v1.2/study') => str_contains($uri, 'code') ? $this->studyByCode() : $this->studiesList(),

            // studyData endpoint: require studyName query parameter
            str_contains($path, 'external/v1/studyData') => \is_string($studyName)
                ? $this->studyDataByName($studyName)
                : throw new \Exception('Missing studyName'),
            str_contains($uri, 'external/v1/protocol') || str_contains($uri, 'external/protocol') => $this->protocolsList(),
            default => throw new \RuntimeException('Unhandled request for path ' . $uri),
        };

        return new MockResponse(
            $json,
            ['http_code' => '200']
        );
    }

    private function animalsList(): string
    {
        return (new Filesystem())->readFile(__DIR__ . '/animals.json');
    }

    // accept optional animal SID (string) and use it deterministically when provided
    private function animalBySid(?string $animalSid = null): string
    {
        // Use deterministic values for consistent test results
        $sid = null !== $animalSid ? (int) $animalSid : 500; // Fixed SID for predictable tests
        $cageSid = 50; // Fixed cage SID
        $datetime = (new \DateTime('-11 minutes'))->format('Y-m-d\TH:i:s\Z');

        return <<<JSON
        {
            "messageCode": "200",
            "messageDesc": "SUCCESS",
            "respObj": [
                {
                    "id": 23431550,
                    "ownerUser": "Jordan Lee",
                    "creatorUser": "Jordan Lee",
                    "guiSid": {$sid},
                    "sex": 1,
                    "birthDate": "2024-08-13",
                    "state": 1,
                    "physicalTag": "1113",
                    "litterSid": null,
                    "strain": "Ang1-A",
                    "protocol": "HFD",
                    "background": null,
                    "generation": null,
                    "receivedDate": null,
                    "endDate": null,
                    "weanDate": "2024-09-03",
                    "tailTagDate": null,
                    "matureDate": "2024-09-24",
                    "procedureDate": null,
                    "phenotype": null,
                    "comment": null,
                    "sourceSupplier": null,
                    "status": null,
                    "category": null,
                    "notice": null,
                    "endType": null,
                    "endReason": null,
                    "createdDateTime": {$datetime},
                    "altId": null,
                    "genotype": "Ang1(fl/+)",
                    "lastUpdatedDateTime": {$datetime},
                    "cageSid": {$cageSid},
                    "cageTag": null,
                    "cageBarcode": null,
                    "studyInfo": []
                }
            ],
            "totalPage": 1,
            "currentPage": 1,
            "totalNum": 1
        }
        JSON;
    }

    private function cagesList(): string
    {
        return <<<'JSON'
        {
            "messageCode": "200",
            "messageDesc": "SUCCESS",
            "respObj": [
                [
                    {
                        "id": 5818459,
                        "ownerUser": "Jordan Lee",
                        "creatorUser": "Jordan Lee",
                        "cageSid": 1,
                        "state": 1,
                        "disposition": "Stock",
                        "setupDate": "2025-04-14",
                        "endDate": null,
                        "cageTag": "1",
                        "cageBarcode": "",
                        "strain": "Shank3",
                        "protocol": "",
                        "keyContact": "",
                        "status": null,
                        "comment": "",
                        "cageType": "5 max",
                        "perDiemRate": null,
                        "createdDateTime": "2025-04-14T19:55:41Z",
                        "lastUpdatedDateTime": "2025-04-17T15:33:52Z",
                        "currResidents": [
                            {
                                "animalSid": 31,
                                "physicalTag": "8831"
                            },
                            {
                                "animalSid": 32,
                                "physicalTag": "8832"
                            },
                            {
                                "animalSid": 33,
                                "physicalTag": "8833"
                            },
                            {
                                "animalSid": 34,
                                "physicalTag": "8834"
                            },
                            {
                                "animalSid": 35,
                                "physicalTag": "8835"
                            }
                        ],
                        "prevResidents": []
                    }
                ],
                [
                    {
                        "id": 5818460,
                        "ownerUser": "Jordan Lee",
                        "creatorUser": "Jordan Lee",
                        "cageSid": 2,
                        "state": 1,
                        "disposition": "Stock",
                        "setupDate": "2025-04-14",
                        "endDate": null,
                        "cageTag": "2",
                        "cageBarcode": "",
                        "strain": "Shank3",
                        "protocol": "",
                        "keyContact": "",
                        "status": null,
                        "comment": "",
                        "cageType": "5 max",
                        "perDiemRate": null,
                        "createdDateTime": "2025-04-14T19:55:41Z",
                        "lastUpdatedDateTime": "2025-04-17T15:33:52Z",
                        "currResidents": [
                            {
                                "animalSid": 36,
                                "physicalTag": "8836"
                            },
                            {
                                "animalSid": 37,
                                "physicalTag": "8837"
                            },
                            {
                                "animalSid": 38,
                                "physicalTag": "8838"
                            },
                            {
                                "animalSid": 39,
                                "physicalTag": "8839"
                            },
                            {
                                "animalSid": 40,
                                "physicalTag": "8840"
                            }
                        ],
                        "prevResidents": []
                    }
                ],
                [
                    {
                        "id": 5818461,
                        "ownerUser": "Jordan Lee",
                        "creatorUser": "Jordan Lee",
                        "cageSid": 3,
                        "state": 1,
                        "disposition": "Stock",
                        "setupDate": "2025-04-14",
                        "endDate": null,
                        "cageTag": "3",
                        "cageBarcode": "",
                        "strain": "Shank3",
                        "protocol": "",
                        "keyContact": "",
                        "status": null,
                        "comment": "",
                        "cageType": "5 max",
                        "perDiemRate": null,
                        "createdDateTime": "2025-04-14T19:55:41Z",
                        "lastUpdatedDateTime": "2025-04-17T15:33:52Z",
                        "currResidents": [
                            {
                                "animalSid": 41,
                                "physicalTag": "8841"
                            },
                            {
                                "animalSid": 42,
                                "physicalTag": "8842"
                            },
                            {
                                "animalSid": 43,
                                "physicalTag": "8843"
                            },
                            {
                                "animalSid": 44,
                                "physicalTag": "8844"
                            },
                            {
                                "animalSid": 45,
                                "physicalTag": "8845"
                            }
                        ],
                        "prevResidents": []
                    }
                ],
                [
                    {
                        "id": 5818462,
                        "ownerUser": "Jordan Lee",
                        "creatorUser": "Jordan Lee",
                        "cageSid": 4,
                        "state": 1,
                        "disposition": "Stock",
                        "setupDate": "2025-04-14",
                        "endDate": null,
                        "cageTag": "4",
                        "cageBarcode": "",
                        "strain": "Shank3",
                        "protocol": "",
                        "keyContact": "",
                        "status": null,
                        "comment": "",
                        "cageType": "5 max",
                        "perDiemRate": null,
                        "createdDateTime": "2025-04-14T19:55:41Z",
                        "lastUpdatedDateTime": "2025-04-17T15:33:52Z",
                        "currResidents": [
                            {
                                "animalSid": 18,
                                "physicalTag": "8818"
                            },
                            {
                                "animalSid": 19,
                                "physicalTag": "8819"
                            },
                            {
                                "animalSid": 20,
                                "physicalTag": "8820"
                            }
                        ],
                        "prevResidents": [
                            {
                                "animalSid": 16,
                                "physicalTag": "8816"
                            },
                            {
                                "animalSid": 17,
                                "physicalTag": "8817"
                            }
                        ]
                    }
                ]
            ],
            "totalPage": 1,
            "currentPage": 1,
            "totalNum": 1
        }
        JSON;
    }

    // accept optional cage id(s) and return a deterministic response
    private function cageById(?string $cageId = null): string
    {
        // Use fixed SID for deterministic tests when not provided
        $sid = 1000;

        return <<<'JSON'
        {
            "messageCode": "200",
            "messageDesc": "SUCCESS",
            "respObj": [
                [
                    {
                        "id": 5818459,
                        "ownerUser": "Jordan Lee",
                        "creatorUser": "Jordan Lee",
                        "cageSid": 1,
                        "state": 1,
                        "disposition": "Stock",
                        "setupDate": "2025-04-14",
                        "endDate": null,
                        "cageTag": "1",
                        "cageBarcode": "",
                        "strain": "Shank3",
                        "protocol": "",
                        "keyContact": "",
                        "status": null,
                        "comment": "",
                        "cageType": "5 max",
                        "perDiemRate": null,
                        "createdDateTime": "2025-04-14T19:55:41Z",
                        "lastUpdatedDateTime": "2025-04-17T15:33:52Z",
                        "currResidents": [
                            {
                                "animalSid": 31,
                                "physicalTag": "8831"
                            },
                            {
                                "animalSid": 32,
                                "physicalTag": "8832"
                            },
                            {
                                "animalSid": 33,
                                "physicalTag": "8833"
                            },
                            {
                                "animalSid": 34,
                                "physicalTag": "8834"
                            },
                            {
                                "animalSid": 35,
                                "physicalTag": "BW-8835"
                            }
                        ],
                        "prevResidents": []
                    }
                ]
            ],
            "totalPage": 1,
            "currentPage": 1,
            "totalNum": 1
        }
        JSON;
    }

    private function studiesList(): string
    {
        return <<<'JSON'
        {
            "messageCode": "200",
            "messageDesc": "SUCCESS",
            "respObj": [
                {
                    "code": "BW_test1",
                    "projectName": "metabolic-disorders-2024",
                    "startDate": "2024-10-01T00:00:00Z",
                    "endDate": "2024-12-31T23:59:59Z",
                    "studyType": "BW",
                    "status": "Active",
                    "details": "<div>First Experiment Following Body weight over 12 weeks in HFD model</div>",
                    "title": "HFD Body Weight Monitoring",
                    "groupName": ["Control", "HFD"],
                    "groupDetails": ["Standard chow diet", "60% fat diet"],
                    "lastUpdatedDateTime": "2024-11-01T14:00:00Z"
                },
                {
                    "code": "BW_test2",
                    "projectName": "metabolic-disorders-2024",
                    "startDate": "2024-09-15T00:00:00Z",
                    "endDate": "2025-01-15T23:59:59Z",
                    "studyType": "BW",
                    "status": "Active",
                    "details": "<div>Longitudinal body weight tracking for diabetes model</div>",
                    "title": "Leptin Receptor Body Weight Study",
                    "groupName": ["WT", "HET", "KO"],
                    "groupDetails": ["Wildtype control", "Heterozygous", "Knockout"],
                    "lastUpdatedDateTime": "2024-11-01T14:00:00Z"
                },
                {
                    "code": "EPM test",
                    "projectName": "autism-anxiety-2024",
                    "startDate": "2024-09-20T00:00:00Z",
                    "endDate": "2024-11-30T23:59:59Z",
                    "studyType": "Behavior",
                    "status": "Active",
                    "details": "<div>Elevated Plus Maze test to assess anxiety-like behavior in Shank3 mice</div>",
                    "title": "EPM Anxiety Assessment",
                    "groupName": ["WT", "HET", "KO"],
                    "groupDetails": ["Wildtype littermate", "Shank3+/-", "Shank3-/-"],
                    "lastUpdatedDateTime": "2024-11-01T14:00:00Z"
                },
                {
                    "code": "SocialInteraction_001",
                    "projectName": "autism-anxiety-2024",
                    "startDate": "2024-10-01T00:00:00Z",
                    "endDate": "2024-12-15T23:59:59Z",
                    "studyType": "Behavior",
                    "status": "Active",
                    "details": "<div>Three-chamber social interaction test in BTBR and control strains</div>",
                    "title": "Social Behavior Assessment",
                    "groupName": ["BTBR", "C57BL6"],
                    "groupDetails": ["BTBR autism model", "C57BL/6J control"],
                    "lastUpdatedDateTime": "2024-11-01T14:00:00Z"
                },
                {
                    "code": "MemoryTest_MWM",
                    "projectName": "neurodegeneration-2024",
                    "startDate": "2024-10-15T00:00:00Z",
                    "endDate": "2024-12-31T23:59:59Z",
                    "studyType": "Behavior",
                    "status": "Active",
                    "details": "<div>Morris Water Maze to assess spatial learning and memory in APP/PS1 mice</div>",
                    "title": "Spatial Memory - MWM",
                    "groupName": ["WT", "APP/PS1"],
                    "groupDetails": ["Wildtype FVB", "APP/PS1 transgenic"],
                    "lastUpdatedDateTime": "2024-11-01T14:00:00Z"
                },
                {
                    "code": "CardioFunction_001",
                    "projectName": "cardiovascular-2024",
                    "startDate": "2024-08-01T00:00:00Z",
                    "endDate": "2024-11-30T23:59:59Z",
                    "studyType": "Physiology",
                    "status": "Active",
                    "details": "<div>Cardiac function assessment using echocardiography</div>",
                    "title": "Cardiovascular Function Study",
                    "groupName": ["Control", "Ang1-deficient"],
                    "groupDetails": ["Ang1(+/+)", "Ang1(fl/fl)"],
                    "lastUpdatedDateTime": "2024-11-01T14:00:00Z"
                },
                {
                    "code": "GlucoseTolerance_001",
                    "projectName": "metabolic-disorders-2024",
                    "startDate": "2024-10-10T00:00:00Z",
                    "endDate": "2024-12-20T23:59:59Z",
                    "studyType": "Metabolic",
                    "status": "Active",
                    "details": "<div>Glucose tolerance test in Lepr mutant mice</div>",
                    "title": "GTT - Leptin Receptor Study",
                    "groupName": ["WT", "Lepr-/-"],
                    "groupDetails": ["Control", "Leptin receptor knockout"],
                    "lastUpdatedDateTime": "2024-11-01T14:00:00Z"
                }
            ]
        }
        JSON;
    }

    // accept optional code parameter for deterministic response
    private function studyByCode(?string $codeParam = null): string
    {
        // Use fixed code for deterministic tests when not provided
        $code = $codeParam ?? 'BW_test1';

        return <<<'JSON'
        {
            "messageCode": "200",
            "messageDesc": "SUCCESS",
            "respObj": [
                {
                    "code": "BW_test1",
                    "projectName": "tswm-yolio-xtx ",
                    "startDate": null,
                    "endDate": null,
                    "studyType": "BW",
                    "status": null,
                    "details": "<div>First Experiment Following Body weight over 5 days</div>",
                    "title": "BW-first-test",
                    "groupName": [],
                    "groupDetails": [],
                    "lastUpdatedDateTime": null
                }
            ]
        }
        JSON;
    }

    // study data: accept optional study name (use provided if present)
    private function studyDataByName(?string $name): string
    {
        $now = new \DateTime();
        // Use provided study name when present, otherwise fallback to default
        $studyName = $name ?? 'Study_BW_test1';

        $newSchedule = $this->getNewScheduledDate($now);
        $newSchedule1 = clone $newSchedule;

        $newSchedule1->add(new \DateInterval('P1D'));
        $newSchedule2 = clone $newSchedule1;
        $newSchedule1 = $newSchedule1->format('Y-m-d\TH:i:s\Z');
        $newSchedule2->add(new \DateInterval('P1D'));
        $newSchedule3 = clone $newSchedule2;
        $newSchedule2 = $newSchedule2->format('Y-m-d\TH:i:s\Z');
        $newSchedule3->add(new \DateInterval('P1D'));
        $newSchedule3 = $newSchedule3->format('Y-m-d\TH:i:s\Z');
        $weight1 = $this->getWeight();
        $weight2 = $this->getWeight();
        $weight3 = $this->getWeight();
        $weight4 = $this->getWeight();
        $weight5 = $this->getWeight();
        $weight6 = $this->getWeight();

        // ensure the study name is JSON-quoted
        $studyNameJson = json_encode($studyName, \JSON_THROW_ON_ERROR);

        return <<<JSON
  {
    "messageCode": "200",
    "messageDesc": "SUCCESS",
    "respObj": [
        {
            "studyName": {$studyNameJson},
            "studyId": 3462,
            "taskId": 10221,
            "taskName": "Body Weight (g)",
            "taskType": "Textbox",
            "taskOccurence": [
                {
                   "scheduleDate": "{$newSchedule1}",
                    "data": [
                        {
                            "animalId": 23431588,
                            "animalSID": "104",
                            "assignedUser": "",
                            "value": "{$weight1}",
                            "data": []
                        },
                        {
                            "animalId": 23431590,
                            "animalSID": "106",
                            "assignedUser": "",
                            "value": "{$weight2}",
                            "data": []
                        },
                        {
                            "animalId": 23431591,
                            "animalSID": "107",
                            "assignedUser": "",
                            "value": "{$weight2}",
                            "data": []
                        },
                        {
                            "animalId": 23431592,
                            "animalSID": "108",
                            "assignedUser": "",
                            "value": "{$weight3}",
                            "data": []
                        },
                        {
                            "animalId": 23431593,
                            "animalSID": "109",
                            "assignedUser": "",
                            "value": "{$weight4}",
                            "data": []
                        }
                    ]
                },
                {
                    "scheduleDate": "{$newSchedule2}",
                    "data": [
                        {
                            "animalId": 23431588,
                            "animalSID": "104",
                            "assignedUser": "",
                            "value": "{$weight1}",
                            "data": []
                        },
                        {
                            "animalId": 23431590,
                            "animalSID": "106",
                            "assignedUser": "",
                            "value": "{$weight1}",
                            "data": []
                        },
                        {
                            "animalId": 23431591,
                            "animalSID": "108",
                            "assignedUser": "",
                            "value": "{$weight6}",
                            "data": []
                        },
                        {
                            "animalId": 23431592,
                            "animalSID": "109",
                            "assignedUser": "",
                            "value": "{$weight4}",
                            "data": []
                        },
                        {
                            "animalId": 23431593,
                            "animalSID": "110",
                            "assignedUser": "",
                            "value": "{$weight2}",
                            "data": []
                        },
                        {
                            "animalId": 23431593,
                            "animalSID": "113",
                            "assignedUser": "",
                            "value": "{$weight1}",
                            "data": []
                        },
                        {
                            "animalId": 23431593,
                            "animalSID": "132",
                            "assignedUser": "",
                            "value": "{$weight5}",
                            "data": []
                        }
                    ]
                },
                {
                    "scheduleDate": "{$newSchedule3}",
                    "data": [
                        {
                            "animalId": 23431588,
                            "animalSID": "104",
                            "assignedUser": "",
                            "value": "{$weight1}",
                            "data": []
                        },
                        {
                            "animalId": 23431590,
                            "animalSID": "106",
                             "assignedUser": "",
                            "value": "{$weight5}",
                            "data": []
                        },
                        {
                            "animalId": 23431591,
                            "animalSID": "107",
                            "assignedUser": "",
                            "value": "{$weight1}",
                            "data": []
                        },
                        {
                            "animalId": 23431592,
                            "animalSID": "108",
                            "assignedUser": "",
                            "value": "{$weight3}",
                            "data": []
                        },
                        {
                            "animalId": 23431593,
                            "animalSID": "109",
                            "assignedUser": "",
                            "value": "{$weight4}",
                            "data": []
                        },
                        {
                            "animalId": 23431593,
                            "animalSID": "110",
                            "assignedUser": "",
                            "value": "{$weight4}",
                            "data": []
                        },
                        {
                            "animalId": 23431593,
                            "animalSID": "113",
                            "assignedUser": "",
                            "value": "{$weight2}",
                            "data": []
                        }
                    ]
                }
            ]
        }
    ]
}
JSON;
    }

    private function getNewScheduledDate(\DateTime $now): \DateTime
    {
        // Start from a deterministic baseline: 62 days before now
        $newestScheduleDate = (clone $now)->sub(new \DateInterval('P62D'));

        // If by any reason the baseline is in the future relative to 1 day ago, fall back further
        $oneDayAgo = (clone $now)->sub(new \DateInterval('P1D'));
        if ($newestScheduleDate > $oneDayAgo) {
            $newestScheduleDate = (clone $now)->sub(new \DateInterval('P124D'));
        }

        $presentMinute = $now->format('i');
        if ($newestScheduleDate->format('i') === $presentMinute) {
            return $newestScheduleDate;
        }

        $newScheduleDate = clone $newestScheduleDate;
        $newScheduleDate->setTime((int) $newestScheduleDate->format('H'), (int) $presentMinute);
        // add 6 hours using the correct ISO 8601 interval spec for hours
        $newScheduleDate->add(new \DateInterval('PT6H'));

        return $newScheduleDate;
    }

    private function protocolsList(): string
    {
        return <<<'JSON'
        {
            "messageCode": "200",
            "messageDesc": "SUCCESS",
            "respObj": [
                {
                    "name": "HFD",
                    "title": "High Fat Diet Study",
                    "state": "Active",
                    "approvalDate": "2024-01-15",
                    "renewalDate": "2025-01-15",
                    "approvedMice": 50,
                    "usedMice": 35,
                    "leftMice": 15,
                    "protocolPainCategory": "B",
                    "status": "Approved",
                    "primaryInvestigator": "Jordan Lee",
                    "creator": "Jordan Lee",
                    "allowLimitBreach": false,
                    "comment": "Metabolic syndrome model"
                },
                {
                    "name": "Behavior",
                    "title": "Behavioral Testing Protocol",
                    "state": "Active",
                    "approvalDate": "2024-02-01",
                    "renewalDate": "2025-02-01",
                    "approvedMice": 100,
                    "usedMice": 45,
                    "leftMice": 55,
                    "protocolPainCategory": "C",
                    "status": "Approved",
                    "primaryInvestigator": "Sarah Johnson",
                    "creator": "Sarah Johnson",
                    "allowLimitBreach": false,
                    "comment": "Anxiety and social behavior"
                },
                {
                    "name": "Cardiovascular",
                    "title": "Cardiovascular Disease Models",
                    "state": "Active",
                    "approvalDate": "2024-03-10",
                    "renewalDate": "2025-03-10",
                    "approvedMice": 75,
                    "usedMice": 60,
                    "leftMice": 15,
                    "protocolPainCategory": "D",
                    "status": "Approved",
                    "primaryInvestigator": "Michael Chen",
                    "creator": "Michael Chen",
                    "allowLimitBreach": false,
                    "comment": "Angiogenesis studies"
                },
                {
                    "name": "SocialBehavior",
                    "title": "Social Behavior and Autism Models",
                    "state": "Active",
                    "approvalDate": "2024-04-20",
                    "renewalDate": "2025-04-20",
                    "approvedMice": 80,
                    "usedMice": 30,
                    "leftMice": 50,
                    "protocolPainCategory": "C",
                    "status": "Approved",
                    "primaryInvestigator": "Emily Rodriguez",
                    "creator": "Emily Rodriguez",
                    "allowLimitBreach": false,
                    "comment": "BTBR and Shank3 models"
                },
                {
                    "name": "Neurodegeneration",
                    "title": "Neurodegenerative Disease Studies",
                    "state": "Active",
                    "approvalDate": "2024-05-01",
                    "renewalDate": "2025-05-01",
                    "approvedMice": 60,
                    "usedMice": 55,
                    "leftMice": 5,
                    "protocolPainCategory": "D",
                    "status": "Approved",
                    "primaryInvestigator": "David Liu",
                    "creator": "David Liu",
                    "allowLimitBreach": true,
                    "comment": "Alzheimer's disease models"
                },
                {
                    "name": "Metabolic",
                    "title": "Metabolic Syndrome and Diabetes",
                    "state": "Active",
                    "approvalDate": "2024-06-15",
                    "renewalDate": "2025-06-15",
                    "approvedMice": 90,
                    "usedMice": 70,
                    "leftMice": 20,
                    "protocolPainCategory": "B",
                    "status": "Approved",
                    "primaryInvestigator": "Anna Kowalski",
                    "creator": "Anna Kowalski",
                    "allowLimitBreach": false,
                    "comment": "Leptin receptor studies"
                }
            ],
            "totalPage": 1,
            "currentPage": 1,
            "totalNum": 6
        }
        JSON;
    }
}
