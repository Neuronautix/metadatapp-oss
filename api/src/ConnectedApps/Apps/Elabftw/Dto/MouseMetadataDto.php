<?php

declare(strict_types=1);

namespace App\Elabftw\Dto;

namespace App\ConnectedApps\Apps\Elabftw\Dto;

use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExtraFieldDto;
use App\Enum\HealthStatus;
use App\Enum\Sex;
use DateMalformedStringException;
use DateTime;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

// todo naming of fck methods
final class MouseMetadataDto
{
    // tood add required etc..
    // todo sanke to camel conversion to have fcking camelCase in this obkct
    public function __construct(
        /** @var array<ExtraFieldDto> $extra_fields */
        #[Assert\Valid]
        public array $extra_fields = [],
    ) {
    }

    public function subjectName(): ?string
    {
        return ($this->extra_fields['ID'] ?? null)?->value;
    }

    // TODO timezone and dcouementation

    /**
     * @throws DateMalformedStringException
     */
    public function subjectDob(): ?DateTimeInterface
    {
        return ($this->extra_fields['DOB']->value ?? null) ? new DateTime($this->extra_fields['DOB']->value) : null;
    }

    public function subjectBodyWeight(): ?string
    {
        return ($this->extra_fields['BW'] ?? null)?->value;
    }

    public function subjectSex(): ?Sex
    {
        return ($this->extra_fields['Sex']->value ?? null) ? Sex::tryFrom($this->extra_fields['Sex']->value) : null;
    }

    public function subjectCageId(): ?string
    {
        return ($this->extra_fields['Cage'] ?? null)?->value;
    }

    public function subjectStrainName(): ?string
    {
        return ($this->extra_fields['Strain'] ?? null)?->value;
    }

    public function subjectGenotypeName(): ?string
    {
        return ($this->extra_fields['Genotype'] ?? null)?->value;
    }

    public function subjectHealthStatus(): ?HealthStatus
    {
        return ($this->extra_fields['HealthStatus']->value ?? null) ? HealthStatus::tryFrom($this->extra_fields['HealthStatus']->value) : null;
    }

    /**
     * @return array<ExtraFieldDto>
     */
    public function getExtraFields(): array
    {
        return $this->extra_fields;
    }
}
