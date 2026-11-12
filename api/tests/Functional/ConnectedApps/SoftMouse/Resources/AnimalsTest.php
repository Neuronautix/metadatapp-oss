<?php

declare(strict_types=1);

namespace App\Tests\Functional\ConnectedApps\SoftMouse\Resources;

use App\ConnectedApps\Apps\SoftMouse\Client\Client;
use App\ConnectedApps\Apps\SoftMouse\Client\ClientInterface;
use App\ConnectedApps\Apps\SoftMouse\Client\Dto\AnimalDto;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AnimalsTest extends WebTestCase
{
    #[Test]
    public function client(): void
    {
        $container = self::getContainer();

        $client = $container->get(Client::class);
        //        $mockResponseJson = <<<'JSON'
        // {
        //    "messageCode": "200",
        //    "messageDesc": "SUCCESS",
        //    "respObj": [
        //        {
        //            "id": 23431550,
        //            "ownerUser": "Damien Huzard",
        //            "creatorUser": "Damien Huzard",
        //            "guiSid": 66,
        //            "sex": 1,
        //            "birthDate": "2024-08-13",
        //            "state": 1,
        //            "physicalTag": "1113",
        //            "litterSid": null,
        //            "strain": "Ang1-A",
        //            "protocol": "HFD",
        //            "background": null,
        //            "generation": null,
        //            "receivedDate": null,
        //            "endDate": null,
        //            "weanDate": "2024-09-03",
        //            "tailTagDate": null,
        //            "matureDate": "2024-09-24",
        //            "procedureDate": null,
        //            "phenotype": null,
        //            "comment": null,
        //            "sourceSupplier": null,
        //            "status": null,
        //            "category": null,
        //            "notice": null,
        //            "endType": null,
        //            "endReason": null,
        //            "createdDateTime": "2025-03-17T15:50:09Z",
        //            "altId": null,
        //            "genotype": "Ang1(fl/+)",
        //            "lastUpdatedDateTime": "2025-03-17T15:50:09Z",
        //            "cageSid": "14",
        //            "cageTag": null,
        //            "cageBarcode": null,
        //            "studyInfo": []
        //        },
        //        {
        //            "id": 23431550,
        //            "ownerUser": "Doctor Yiolyo",
        //            "creatorUser": "Damien Huzard",
        //            "guiSid": 67,
        //            "sex": 1,
        //            "birthDate": "2024-08-13",
        //            "state": 1,
        //            "physicalTag": "1113",
        //            "litterSid": null,
        //            "strain": "Ang1-A",
        //            "protocol": "HFD",
        //            "background": null,
        //            "generation": null,
        //            "receivedDate": null,
        //            "endDate": null,
        //            "weanDate": "2024-09-03",
        //            "tailTagDate": null,
        //            "matureDate": "2024-09-24",
        //            "procedureDate": null,
        //            "phenotype": null,
        //            "comment": null,
        //            "sourceSupplier": null,
        //            "status": null,
        //            "category": null,
        //            "notice": null,
        //            "endType": null,
        //            "endReason": null,
        //            "createdDateTime": "2025-03-17T15:50:09Z",
        //            "altId": null,
        //            "genotype": "Ang1(fl/+)",
        //            "lastUpdatedDateTime": "2025-03-17T15:50:09Z",
        //            "cageSid": "14",
        //            "cageTag": null,
        //            "cageBarcode": null,
        //            "studyInfo": []
        //        }
        //    ],
        //    "totalPage": 1,
        //    "currentPage": 1,
        //    "totalNum": 1
        // }
        // JSON;
        //
        //        $mockResponse = new MockResponse($mockResponseJson, [
        //            'http_code' => 200,
        //            'response_headers' => ['Content-Type: application/json'],
        //        ]);
        //
        //        $httpClient = new MockHttpClient($mockResponse, 'https://softmouse.com/api');
        $softMouseClient = $container->get(ClientInterface::class);
        $animals = $softMouseClient->animals()->all();

        $client->animals()->all();
        $this->assertNotEmpty($animals);
        $this->assertIsArray($animals);
        $this->assertCount(16, $animals);
        $this->assertContainsOnlyInstancesOf(AnimalDto::class, $animals);
    }
}
