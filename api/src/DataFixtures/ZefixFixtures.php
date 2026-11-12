<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\PlateauFactory;
use App\DataFixtures\Factory\PoolFactory;
use App\DataFixtures\Factory\RackFactory;
use App\DataFixtures\Factory\RoomFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\ZebrafishBatchFactory;
use App\DataFixtures\Factory\ZebrafishLineFactory;
use App\Enum\PoolStatus;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\Enum\ZebrafishLineStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class ZefixFixtures extends Fixture
{
    private const DEMO_ACCOUNT_NAME = 'Zefix Demo Facility';
    private const DEMO_ADMIN_USER_EMAIL = 'zefix.demo@metadatapp.net';
    private const DEMO_USER_EMAIL = 'zefix.viewer@metadatapp.net';

    private const HIERARCHY = [
        [
            'name' => 'North Aquatics Platform',
            'code' => 'ZF-NORTH',
            'rooms' => [
                [
                    'name' => 'Room 101',
                    'racks' => [
                        [
                            'code' => 'N-01',
                            'pools' => [
                                ['position' => 'A1', 'capacity' => 80, 'status' => PoolStatus::OCCUPIED],
                                ['position' => 'A2', 'capacity' => 80, 'status' => PoolStatus::AVAILABLE],
                                ['position' => 'B1', 'capacity' => 60, 'status' => PoolStatus::MAINTENANCE],
                            ],
                        ],
                        [
                            'code' => 'N-02',
                            'pools' => [
                                ['position' => 'A1', 'capacity' => 100, 'status' => PoolStatus::OCCUPIED],
                                ['position' => 'A2', 'capacity' => 100, 'status' => PoolStatus::AVAILABLE],
                                ['position' => 'B1', 'capacity' => 60, 'status' => PoolStatus::AVAILABLE],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Room 102',
                    'racks' => [
                        [
                            'code' => 'N-03',
                            'pools' => [
                                ['position' => 'A1', 'capacity' => 90, 'status' => PoolStatus::AVAILABLE],
                                ['position' => 'A2', 'capacity' => 90, 'status' => PoolStatus::AVAILABLE],
                                ['position' => 'B1', 'capacity' => 50, 'status' => PoolStatus::MAINTENANCE],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'name' => 'South Aquatics Platform',
            'code' => 'ZF-SOUTH',
            'rooms' => [
                [
                    'name' => 'Room 201',
                    'racks' => [
                        [
                            'code' => 'S-01',
                            'pools' => [
                                ['position' => 'A1', 'capacity' => 70, 'status' => PoolStatus::OCCUPIED],
                                ['position' => 'A2', 'capacity' => 70, 'status' => PoolStatus::AVAILABLE],
                                ['position' => 'B1', 'capacity' => 70, 'status' => PoolStatus::INACTIVE],
                            ],
                        ],
                        [
                            'code' => 'S-02',
                            'pools' => [
                                ['position' => 'A1', 'capacity' => 120, 'status' => PoolStatus::AVAILABLE],
                                ['position' => 'A2', 'capacity' => 120, 'status' => PoolStatus::OCCUPIED],
                                ['position' => 'B1', 'capacity' => 80, 'status' => PoolStatus::AVAILABLE],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Imaging Aquatics Platform',
            'code' => 'ZF-IMG',
            'rooms' => [
                [
                    'name' => 'Microscopy Suite',
                    'racks' => [
                        [
                            'code' => 'I-01',
                            'pools' => [
                                ['position' => 'A1', 'capacity' => 50, 'status' => PoolStatus::OCCUPIED],
                                ['position' => 'A2', 'capacity' => 50, 'status' => PoolStatus::AVAILABLE],
                            ],
                        ],
                        [
                            'code' => 'I-02',
                            'pools' => [
                                ['position' => 'A1', 'capacity' => 50, 'status' => PoolStatus::AVAILABLE],
                                ['position' => 'A2', 'capacity' => 50, 'status' => PoolStatus::MAINTENANCE],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Quarantine Aquatics Platform',
            'code' => 'ZF-QUAR',
            'rooms' => [
                [
                    'name' => 'Quarantine Bay',
                    'racks' => [
                        [
                            'code' => 'Q-01',
                            'pools' => [
                                ['position' => 'A1', 'capacity' => 40, 'status' => PoolStatus::OCCUPIED],
                                ['position' => 'A2', 'capacity' => 40, 'status' => PoolStatus::AVAILABLE],
                            ],
                        ],
                        [
                            'code' => 'Q-02',
                            'pools' => [
                                ['position' => 'A1', 'capacity' => 40, 'status' => PoolStatus::INACTIVE],
                                ['position' => 'A2', 'capacity' => 40, 'status' => PoolStatus::AVAILABLE],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    private const LINES = [
        [
            'key' => 'gcamp',
            'name' => 'Tg(elavl3:GCaMP6s)',
            'genotype' => 'Tg(elavl3:GCaMP6s)zf410',
            'purpose' => 'Pan-neuronal calcium reporter line used for imaging sessions.',
            'status' => ZebrafishLineStatus::ACTIVE,
            'createdAt' => '2024-01-10T09:00:00+00:00',
        ],
        [
            'key' => 'ins',
            'name' => 'Tg(ins:mCherry)',
            'genotype' => 'Tg(ins:mCherry)s960',
            'purpose' => 'Beta-cell reporter supporting endocrine pancreas workflows.',
            'status' => ZebrafishLineStatus::ACTIVE,
            'createdAt' => '2024-02-15T08:30:00+00:00',
        ],
        [
            'key' => 'mpx',
            'name' => 'Tg(mpx:GFP)',
            'genotype' => 'Tg(mpx:GFP)i114',
            'purpose' => 'Inflammation and innate immunity follow-up cohort.',
            'status' => ZebrafishLineStatus::ACTIVE,
            'createdAt' => '2024-03-05T11:15:00+00:00',
        ],
        [
            'key' => 'gfap',
            'name' => 'Tg(gfap:EGFP)',
            'genotype' => 'Tg(gfap:EGFP)mi2001',
            'purpose' => 'Paused glial reporter awaiting re-derivation.',
            'status' => ZebrafishLineStatus::PAUSED,
            'createdAt' => '2024-04-02T14:00:00+00:00',
        ],
        [
            'key' => 'kdrl',
            'name' => 'Tg(kdrl:ras-mCherry)',
            'genotype' => 'Tg(kdrl:ras-mCherry)s896',
            'purpose' => 'Archived vascular reporter preserved for historical datasets.',
            'status' => ZebrafishLineStatus::ARCHIVED,
            'createdAt' => '2023-11-20T10:45:00+00:00',
        ],
    ];

    private const BATCHES = [
        ['line' => 'gcamp', 'pool' => 'N-01:A1', 'birthDate' => '2024-02-01', 'maleCount' => 12, 'femaleCount' => 14, 'unknownSexCount' => 4, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE],
        ['line' => 'ins', 'pool' => 'N-02:A1', 'birthDate' => '2024-03-18', 'maleCount' => 10, 'femaleCount' => 11, 'unknownSexCount' => 2, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE],
        ['line' => 'mpx', 'pool' => 'S-01:A1', 'birthDate' => '2024-05-07', 'maleCount' => 9, 'femaleCount' => 9, 'unknownSexCount' => 0, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE],
        ['line' => 'gcamp', 'pool' => 'S-02:A2', 'birthDate' => '2024-04-11', 'maleCount' => 16, 'femaleCount' => 18, 'unknownSexCount' => 3, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE],
        ['line' => 'ins', 'pool' => 'I-01:A1', 'birthDate' => '2024-06-03', 'maleCount' => 8, 'femaleCount' => 9, 'unknownSexCount' => 1, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ACTIVE],
        ['line' => 'gfap', 'pool' => 'Q-01:A1', 'birthDate' => '2024-06-20', 'maleCount' => 7, 'femaleCount' => 8, 'unknownSexCount' => 1, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::QUARANTINED],
        ['line' => 'kdrl', 'pool' => 'N-03:A2', 'birthDate' => '2023-12-12', 'maleCount' => 15, 'femaleCount' => 16, 'unknownSexCount' => 1, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::COMPLETED],
        ['line' => 'kdrl', 'pool' => 'Q-02:A1', 'birthDate' => '2023-10-01', 'maleCount' => 8, 'femaleCount' => 7, 'unknownSexCount' => 0, 'lifecycleStatus' => ZebrafishBatchLifecycleStatus::ARCHIVED],
    ];

    public function load(ObjectManager $manager): void
    {
        $account = AccountFactory::createOne([
            'name' => self::DEMO_ACCOUNT_NAME,
        ]);

        UserFactory::createOneAdmin([
            'email' => self::DEMO_ADMIN_USER_EMAIL,
            'firstName' => 'Zefix',
            'lastName' => 'Operator',
            'account' => $account,
        ]);
        UserFactory::createOne([
            'email' => self::DEMO_USER_EMAIL,
            'firstName' => 'Zefix',
            'lastName' => 'Viewer',
            'account' => $account,
        ]);

        $pools = [];
        foreach (self::HIERARCHY as $plateauData) {
            $plateau = PlateauFactory::createOne([
                'name' => $plateauData['name'],
                'code' => $plateauData['code'],
                'account' => $account,
            ]);

            foreach ($plateauData['rooms'] as $roomData) {
                $room = RoomFactory::createOne([
                    'name' => $roomData['name'],
                    'plateau' => $plateau,
                    'account' => $account,
                ]);

                foreach ($roomData['racks'] as $rackData) {
                    $rack = RackFactory::createOne([
                        'code' => $rackData['code'],
                        'room' => $room,
                        'account' => $account,
                    ]);

                    foreach ($rackData['pools'] as $poolData) {
                        $pool = PoolFactory::createOne([
                            'rack' => $rack,
                            'position' => $poolData['position'],
                            'capacity' => $poolData['capacity'],
                            'status' => $poolData['status'],
                            'account' => $account,
                        ]);

                        $pools[$rackData['code'] . ':' . $poolData['position']] = $pool;
                    }
                }
            }
        }

        $lines = [];
        foreach (self::LINES as $lineData) {
            $lines[$lineData['key']] = ZebrafishLineFactory::createOne([
                'name' => $lineData['name'],
                'genotype' => $lineData['genotype'],
                'purpose' => $lineData['purpose'],
                'status' => $lineData['status'],
                'createdAt' => new \DateTimeImmutable($lineData['createdAt']),
                'account' => $account,
            ]);
        }

        foreach (self::BATCHES as $batchData) {
            ZebrafishBatchFactory::createOne([
                'line' => $lines[$batchData['line']],
                'pool' => $pools[$batchData['pool']],
                'birthDate' => new \DateTimeImmutable($batchData['birthDate']),
                'maleCount' => $batchData['maleCount'],
                'femaleCount' => $batchData['femaleCount'],
                'unknownSexCount' => $batchData['unknownSexCount'],
                'totalPopulation' => $batchData['maleCount'] + $batchData['femaleCount'] + $batchData['unknownSexCount'],
                'lifecycleStatus' => $batchData['lifecycleStatus'],
                'account' => $account,
            ]);
        }
    }
}
