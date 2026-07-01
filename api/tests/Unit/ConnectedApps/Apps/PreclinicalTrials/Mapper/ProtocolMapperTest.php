<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\PreclinicalTrials\Mapper;

use App\ConnectedApps\Apps\PreclinicalTrials\Mapper\ProtocolMapper;
use App\Entity\Account;
use App\Entity\ConnectedApp;
use App\Entity\Project;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ProtocolMapperTest extends TestCase
{
    #[Test]
    public function mapToProjectFlattensProtocolDetailsIntoSortableProjectFields(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $connectedApp->method('getAccount')->willReturn($this->createMock(Account::class));
        $connectedApp->method('getUser')->willReturn($this->createMock(User::class));

        $project = (new ProtocolMapper())->mapToProject($connectedApp, [
            'id' => 119,
            'pct_id' => 'PCTE0000119',
            'registration_date' => '2018-05-02',
            'status' => 'published',
            'details' => [
                ['key' => 'title', 'value' => 'Immunogenicity of T-independent antigen'],
                ['key' => 'study_status', 'value' => 'active'],
                ['key' => 'research_field', 'value' => 'Immunology'],
                ['key' => 'hypothesis', 'value' => "Repeated antigen administration will not elevate antibodies.\n"],
                ['key' => 'start_date', 'value' => '2018-05-09'],
                ['key' => 'end_date', 'value' => '2018-06-20'],
                ['key' => 'species', 'value' => 'mouse'],
                ['key' => 'strain', 'value' => 'Balb/c'],
                ['key' => 'sex', 'value' => 'female'],
                ['key' => 'sum_of_animals', 'value' => '15'],
                ['key' => 'study_centre', 'value' => [
                    ['name' => 'University of North Carolina at Chapel Hill', 'city' => 'Chapel Hill', 'country' => 'US'],
                ]],
                ['key' => 'study_arms', 'value' => [
                    ['type' => 'sham', 'number' => '5', 'intervention' => 'saline'],
                    ['type' => 'intervention', 'number' => '10', 'intervention' => 'antigen of interest'],
                ]],
                ['key' => 'primary_readout_parameter', 'value' => 'Safety: antibody concentration'],
            ],
        ], new Project());

        $this->assertSame('preclinicaltrials:PCTE0000119', $project->getExternalId());
        $this->assertSame('PCTE0000119 - Immunogenicity of T-independent antigen', $project->getName());
        $this->assertSame('Repeated antigen administration will not elevate antibodies.', $project->getGoal());
        $this->assertSame('2018-05-09', $project->getStartAt()?->format('Y-m-d'));
        $this->assertSame('2018-06-20', $project->getEndAt()?->format('Y-m-d'));
        $this->assertSame('https://preclinicaltrials.eu/protocol/PCTE0000119', $project->getRepositoryLink());
        $this->assertStringContainsString('Research field: Immunology', (string) $project->getDescription());
        $this->assertStringContainsString('Species: mouse', (string) $project->getDescription());
        $this->assertStringContainsString('University of North Carolina at Chapel Hill, Chapel Hill, US', (string) $project->getDescription());
        $this->assertStringContainsString('intervention (10): antigen of interest', (string) $project->getDescription());
    }
}
