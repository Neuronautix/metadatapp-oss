<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Resources;

use App\ConnectedApps\Apps\SoftMouse\Client\Dto\SoftMouseResponseDto;
use App\ConnectedApps\Apps\SoftMouse\Client\Dto\StudyDataDto;
use App\ConnectedApps\Apps\SoftMouse\Client\SoftMouseHttpClientInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class StudyData
{
    private const ENDPOINT = 'external/v1/studyData';

    public function __construct(private readonly SoftMouseHttpClientInterface $httpClient)
    {
    }

    /**
     * @return StudyDataDto[]
     */
    private function denormalizeResponse(SoftMouseResponseDto $softMouseResponse): array
    {
        //        $serializer = new Serializer(
        //            [new ObjectNormalizer(), new ArrayDenormalizer()],
        //            [new JsonEncoder()],
        //        );
        /** @var PropertyListExtractorInterface[] $listExtractors */
        $listExtractors = [new PhpDocExtractor(), new ReflectionExtractor()];
        /** @var PropertyTypeExtractorInterface[] $typeExtractors */
        $typeExtractors = $listExtractors;
        $propertyTypeExtractor = new PropertyInfoExtractor(
            $listExtractors,
            $typeExtractors
        );

        $serializer = new Serializer(
            [new ObjectNormalizer(null, null, null, $propertyTypeExtractor), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );

        if ([] === $softMouseResponse->respObj) {
            return [];
        }

        return $serializer->denormalize($softMouseResponse->respObj, StudyDataDto::class . '[]');
    }

    public function get(string $studyName): ?StudyDataDto
    {
        $softMouseResponse = $this->httpClient->sendRequest('GET', self::ENDPOINT, [
            'query' => ['studyName' => $studyName],
        ]);

        return $this->denormalizeResponse($softMouseResponse)[0] ?? null;
    }
}
