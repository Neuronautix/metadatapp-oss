<?php

declare(strict_types=1);

namespace App\CurateGpt\Client;

use App\CurateGpt\Client\Dto\CurateGptCandidateDto;
use App\CurateGpt\Client\Dto\CurateGptSearchRequestDto;

/**
 * Contract for communicating with a CurateGPT-compatible curation backend.
 *
 * Implementations must be stateless and must never write to the Metadatapp
 * database – persistence of suggestions is handled by CurateGptService.
 */
interface CurateGptClientInterface
{
    /**
     * Search for candidate ontology terms that match the given query.
     *
     * @return CurateGptCandidateDto[]
     */
    public function search(CurateGptSearchRequestDto $request): array;
}
