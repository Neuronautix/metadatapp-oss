<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpTool;
use App\Demo\Sensors\SensorAlarmStatusView;
use App\Demo\Sensors\SensorHealthView;
use App\Demo\Sensors\SensorHistoryView;
use App\Demo\Sensors\SensorMetricView;
use App\Demo\Sensors\SensorObservationView;
use App\Demo\Sensors\SensorThresholdStatusView;
use App\Entity\Cage;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\Subject;
use App\Resource\FairAssessment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class McpToolRegistrationTest extends TestCase
{
    #[Test]
    public function itDeclaresAllExpectedMcpTools(): void
    {
        $tools = $this->extractToolNames([
            Subject::class,
            Project::class,
            Cage::class,
            Experiment::class,
            FairAssessment::class,
            SensorHealthView::class,
            SensorMetricView::class,
            SensorAlarmStatusView::class,
            SensorObservationView::class,
            SensorThresholdStatusView::class,
            SensorHistoryView::class,
        ]);

        $expected = [
            'create_project',
            'get_cage',
            'get_experiment',
            'get_fair_assessment',
            'get_investigation',
            'get_live_sensor_alarm_status',
            'get_live_sensor_health',
            'get_live_sensor_history',
            'get_live_sensor_metric',
            'get_live_sensor_observation',
            'get_live_sensor_threshold_status',
            'get_project',
            'get_study',
            'get_subject',
            'list_cages',
            'list_experiments',
            'list_investigations',
            'list_mice',
            'list_projects',
            'list_studies',
            'list_zebrafish',
        ];

        sort($tools);
        sort($expected);

        self::assertSame($expected, $tools);
    }

    #[Test]
    public function itDeclaresCreateProjectAsPostTool(): void
    {
        $apiResource = (new \ReflectionClass(Project::class))
            ->getAttributes(ApiResource::class)[0]
            ->newInstance();

        foreach ($apiResource->getMcp() ?? [] as $mcp) {
            if (!$mcp instanceof McpTool || 'create_project' !== $mcp->getName()) {
                continue;
            }

            self::assertSame('POST', $mcp->getMethod());
            self::assertSame('/projects', $mcp->getUriTemplate());

            return;
        }

        self::fail('The create_project MCP tool operation is not registered on Project.');
    }

    /**
     * @param list<class-string> $classes
     *
     * @return list<string>
     */
    private function extractToolNames(array $classes): array
    {
        $tools = [];

        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getAttributes(McpTool::class) as $attribute) {
                $tools[] = $attribute->newInstance()->getName();
            }

            $apiResourceAttributes = $reflection->getAttributes(ApiResource::class);
            if ([] === $apiResourceAttributes) {
                continue;
            }

            /** @var ApiResource $apiResource */
            $apiResource = $apiResourceAttributes[0]->newInstance();
            foreach ($apiResource->getMcp() ?? [] as $mcp) {
                if ($mcp instanceof McpTool) {
                    $tools[] = $mcp->getName();
                }
            }
        }

        return array_values(array_unique($tools));
    }
}
