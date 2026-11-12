<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Mcp;

use App\AI\Mcp\McpBridgeService;
use App\AI\Mcp\McpToolCallResult;
use App\AI\Mcp\ToolAccessPolicy;
use App\Service\SensorAgentClientInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class McpBridgeServiceTest extends TestCase
{
    private SensorAgentClientInterface&MockObject $sensorAgent;

    protected function setUp(): void
    {
        $this->sensorAgent = $this->createMock(SensorAgentClientInterface::class);
    }

    #[Test]
    public function itReturnsEmptyWhenBridgeIsDisabled(): void
    {
        $bridge = $this->makeBridge(enabled: false);

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'what is the sensor temperature?']],
        ]);

        self::assertSame([], $results);
        self::assertFalse($bridge->isEnabled());
    }

    #[Test]
    public function itReturnsEmptyWhenTaskTypeIsNotChat(): void
    {
        $this->sensorAgent->expects(self::never())->method('getHealth');
        $this->sensorAgent->expects(self::never())->method('getLatestObservation');
        $this->sensorAgent->expects(self::never())->method('getThresholdStatus');

        $bridge = $this->makeBridge();

        $results = $bridge->callApprovedTools('draft_query_filters', [
            'messages' => [['role' => 'user', 'content' => 'sensor temperature']],
        ]);

        self::assertSame([], $results);
    }

    #[Test]
    public function itReturnsEmptyWhenNoKeywordMatches(): void
    {
        $bridge = $this->makeBridge();

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'draft search filters for mice']],
        ]);

        self::assertSame([], $results);
    }

    #[Test]
    public function itCallsOnlyLatestObservationOnGenericSensorKeyword(): void
    {
        $this->sensorAgent->expects(self::never())->method('getHealth');
        $this->sensorAgent->expects(self::once())->method('getLatestObservation')->willReturn(['temperatureC' => 21.5]);
        $this->sensorAgent->expects(self::never())->method('getThresholdStatus');

        $bridge = $this->makeBridge();

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'Show me the current sensor data.']],
        ]);

        self::assertCount(1, $results);
        self::assertContainsOnlyInstancesOf(McpToolCallResult::class, $results);
        self::assertSame('get_live_sensor_observation', $results[0]->toolName);
        self::assertTrue($results[0]->success);
    }

    #[Test]
    public function itCallsOnlyObservationToolOnSpecificTemperatureKeyword(): void
    {
        $this->sensorAgent->expects(self::never())->method('getHealth');
        $this->sensorAgent->expects(self::once())->method('getLatestObservation')->willReturn(['temperatureC' => 22.0]);
        $this->sensorAgent->expects(self::never())->method('getThresholdStatus');

        $bridge = $this->makeBridge();

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'What is the current temperature?']],
        ]);

        self::assertCount(1, $results);
        self::assertSame('get_live_sensor_observation', $results[0]->toolName);
        self::assertTrue($results[0]->success);
    }

    #[Test]
    public function itCallsOnlyHealthToolOnHealthyKeyword(): void
    {
        $this->sensorAgent->expects(self::once())->method('getHealth')->willReturn(['status' => 'ready']);
        $this->sensorAgent->expects(self::never())->method('getLatestObservation');
        $this->sensorAgent->expects(self::never())->method('getThresholdStatus');

        $bridge = $this->makeBridge();

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'Is the Raspberry Pi sensor healthy?']],
        ]);

        self::assertCount(1, $results);
        self::assertSame('get_live_sensor_health', $results[0]->toolName);
        self::assertTrue($results[0]->success);
    }

    #[Test]
    public function itCallsOnlyThresholdToolOnSpecificAlarmKeyword(): void
    {
        $this->sensorAgent->expects(self::never())->method('getHealth');
        $this->sensorAgent->expects(self::never())->method('getLatestObservation');
        $this->sensorAgent->expects(self::once())->method('getThresholdStatus')->willReturn(['alarmActive' => true]);

        $bridge = $this->makeBridge();

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'Is there an active alarm?']],
        ]);

        self::assertCount(1, $results);
        self::assertSame('get_live_sensor_threshold_status', $results[0]->toolName);
        self::assertTrue($results[0]->success);
    }

    #[Test]
    public function itCallsMinimalSubsetForMultipleSpecificKeywords(): void
    {
        $this->sensorAgent->expects(self::never())->method('getHealth');
        $this->sensorAgent->expects(self::once())->method('getLatestObservation')->willReturn(['humidityPct' => 55.0]);
        $this->sensorAgent->expects(self::once())->method('getThresholdStatus')->willReturn(['alarmActive' => false]);

        $bridge = $this->makeBridge();

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'What is the humidity and is the alarm active?']],
        ]);

        self::assertCount(2, $results);
        $toolNames = array_map(static fn (McpToolCallResult $r): string => $r->toolName, $results);
        self::assertContains('get_live_sensor_observation', $toolNames);
        self::assertContains('get_live_sensor_threshold_status', $toolNames);
    }

    #[Test]
    public function itReturnsSanitizedErrorAndDoesNotLeakExceptionDetails(): void
    {
        $this->sensorAgent->expects(self::never())->method('getHealth');
        $this->sensorAgent->expects(self::once())->method('getLatestObservation')->willThrowException(new \RuntimeException('http://internal-host/api: Agent unavailable.'));
        $this->sensorAgent->expects(self::never())->method('getThresholdStatus');

        $bridge = $this->makeBridge();

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'What is the sensor temperature?']],
        ]);

        self::assertCount(1, $results);
        self::assertFalse($results[0]->success);
        self::assertSame('Tool call failed.', $results[0]->error);
        self::assertStringNotContainsString('internal-host', $results[0]->error ?? '');
        self::assertSame([], $results[0]->data);
    }

    #[Test]
    public function itSkipsToolsThatAreNotInApprovedList(): void
    {
        $this->sensorAgent->expects(self::never())->method('getHealth');

        $bridge = $this->makeBridgeWithApprovedTools(
            enabled: true,
            policies: [
                new ToolAccessPolicy('get_live_sensor_threshold_status', allowsRead: true, allowsWrite: false, requiresHumanApproval: false),
            ],
        );

        $this->sensorAgent->expects(self::once())->method('getThresholdStatus')->willReturn(['alarmActive' => false]);

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'Check the sensor threshold status.']],
        ]);

        self::assertCount(1, $results);
        self::assertSame('get_live_sensor_threshold_status', $results[0]->toolName);
    }

    #[Test]
    public function itSkipsToolsThatRequireHumanApproval(): void
    {
        $this->sensorAgent->expects(self::never())->method('getHealth');
        $this->sensorAgent->expects(self::never())->method('getLatestObservation');
        $this->sensorAgent->expects(self::never())->method('getThresholdStatus');

        $bridge = $this->makeBridgeWithApprovedTools(
            enabled: true,
            policies: [
                new ToolAccessPolicy('get_live_sensor_health', allowsRead: true, allowsWrite: false, requiresHumanApproval: true),
                new ToolAccessPolicy('get_live_sensor_observation', allowsRead: true, allowsWrite: false, requiresHumanApproval: true),
                new ToolAccessPolicy('get_live_sensor_threshold_status', allowsRead: true, allowsWrite: false, requiresHumanApproval: true),
            ],
        );

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'Show me sensor data.']],
        ]);

        self::assertSame([], $results);
    }

    #[Test]
    public function itEnforcesReadOnlyAndSkipsWriteEnabledTools(): void
    {
        $this->sensorAgent->expects(self::never())->method('getHealth');
        $this->sensorAgent->expects(self::never())->method('getLatestObservation');
        $this->sensorAgent->expects(self::never())->method('getThresholdStatus');

        $bridge = $this->makeBridgeWithApprovedTools(
            enabled: true,
            policies: $this->writeCapableSensorPolicies(),
        );

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'Show me sensor data.']],
        ]);

        self::assertSame([], $results);
    }

    #[Test]
    public function provenanceEntryContainsExpectedFields(): void
    {
        $this->sensorAgent->expects(self::never())->method('getHealth');
        $this->sensorAgent->expects(self::once())->method('getLatestObservation')->willReturn(['humidityPct' => 55.0]);
        $this->sensorAgent->expects(self::once())->method('getThresholdStatus')->willReturn(['alarmActive' => false]);

        $bridge = $this->makeBridge();

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'humidity alarm']],
        ]);

        self::assertCount(2, $results);

        foreach ($results as $result) {
            $entry = $result->toProvenanceEntry();
            self::assertArrayHasKey('label', $entry);
            self::assertArrayHasKey('value', $entry);
            self::assertArrayHasKey('detail', $entry);
            self::assertArrayHasKey('source', $entry);
            self::assertStringStartsWith('mcp:', $entry['source']);
            self::assertArrayHasKey('tool', $entry);
            self::assertArrayHasKey('success', $entry);
            self::assertArrayHasKey('data', $entry);
            self::assertArrayHasKey('error', $entry);
            self::assertArrayHasKey('callDurationMs', $entry);
        }
    }

    #[Test]
    public function failedProvenanceEntryHasSanitizedErrorAndEmptyData(): void
    {
        $this->sensorAgent->expects(self::never())->method('getHealth');
        $this->sensorAgent->expects(self::once())->method('getLatestObservation')->willThrowException(new \RuntimeException('offline'));
        $this->sensorAgent->expects(self::never())->method('getThresholdStatus');

        $bridge = $this->makeBridge();

        $results = $bridge->callApprovedTools('assistant_chat', [
            'messages' => [['role' => 'user', 'content' => 'What is the pressure?']],
        ]);

        self::assertCount(1, $results);
        $entry = $results[0]->toProvenanceEntry();
        self::assertSame([], $entry['data']);
        self::assertSame('Tool call failed.', $entry['error']);
    }

    private function makeBridge(bool $enabled = true): McpBridgeService
    {
        return $this->makeBridgeWithApprovedTools(
            enabled: $enabled,
            policies: [
                new ToolAccessPolicy('get_live_sensor_health', allowsRead: true, allowsWrite: false, requiresHumanApproval: false),
                new ToolAccessPolicy('get_live_sensor_observation', allowsRead: true, allowsWrite: false, requiresHumanApproval: false),
                new ToolAccessPolicy('get_live_sensor_threshold_status', allowsRead: true, allowsWrite: false, requiresHumanApproval: false),
            ],
        );
    }

    /**
     * @param list<ToolAccessPolicy> $policies
     */
    private function makeBridgeWithApprovedTools(bool $enabled, array $policies): McpBridgeService
    {
        return new McpBridgeService(
            enabled: $enabled,
            approvedTools: $policies,
            sensorAgent: $this->sensorAgent,
            logger: new NullLogger(),
        );
    }

    /**
     * @return list<ToolAccessPolicy>
     */
    private function writeCapableSensorPolicies(): array
    {
        return [
            new ToolAccessPolicy('get_live_sensor_health', allowsRead: true, allowsWrite: true, requiresHumanApproval: true),
            new ToolAccessPolicy('get_live_sensor_observation', allowsRead: true, allowsWrite: true, requiresHumanApproval: true),
            new ToolAccessPolicy('get_live_sensor_threshold_status', allowsRead: true, allowsWrite: true, requiresHumanApproval: true),
        ];
    }
}
