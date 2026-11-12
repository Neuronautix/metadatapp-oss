<?php

declare(strict_types=1);

namespace App\Enum;

enum CurationTaskType: string
{
    case MissingRequiredField = 'missing_required_field';
    case ValueNormalization = 'value_normalization';
    case OntologyCandidate = 'ontology_candidate';
    case CrossFieldConsistency = 'cross_field_consistency';
}
