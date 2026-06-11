<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Curation;

use App\AI\Curation\PatchProposal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PatchProposalTest extends TestCase
{
    #[Test]
    public function itRequiresProvenanceForEveryProposal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Patch proposals must include provenance.');

        new PatchProposal(
            operations: [['op' => 'replace', 'path' => '/strain', 'value' => 'C57BL/6J']],
            rationale: 'Normalize strain casing.',
            provenance: [],
        );
    }
}
