<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps;

use App\ConnectedApps\ConnectedAppServiceFactory;
use App\ConnectedApps\ConnectedAppServiceInterface;
use App\Enum\AppCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ConnectedAppServiceFactoryTest extends TestCase
{
    #[Test]
    public function createServiceReturnsServingService(): void
    {
        $appCode = AppCode::Elabftw;

        $service1 = $this->createMock(ConnectedAppServiceInterface::class);
        $service1->expects($this->any())
            ->method('supportsCode')
            ->with($appCode)
            ->willReturn(false)
        ;

        $service2 = $this->createMock(ConnectedAppServiceInterface::class);
        $service2->expects($this->any())
            ->method('supportsCode')
            ->with($appCode)
            ->willReturn(true)
        ;

        $factory = new ConnectedAppServiceFactory([$service1, $service2]);

        $result = $factory->createService($appCode);

        $this->assertSame($service2, $result);
    }

    #[Test]
    public function createServiceThrowsExceptionWhenNoServiceFound(): void
    {
        $appCode = AppCode::Elabftw;

        $service = $this->createMock(ConnectedAppServiceInterface::class);
        $service->expects($this->any())
            ->method('supportsCode')
            ->willReturn(false)
        ;

        $factory = new ConnectedAppServiceFactory([$service]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No connected app service found for app code: elabftw');

        $factory->createService($appCode);
    }
}
