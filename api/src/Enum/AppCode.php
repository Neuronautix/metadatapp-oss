<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum AppCode: string
{
    use EnumApiResourceTrait;

    case SoftMouse = 'softmouse';
    case Elabftw = 'elabftw';
    case Osf = 'osf';
    case ProtocolIo = 'protocolio';
    case PreclinicalTrials = 'preclinicaltrials';
    case Cedar = 'cedar';
    case BioPortal = 'bioportal';
    case Fair3r = 'fair3r';
    case Tecniplast = 'tecniplast';
    case SensorAgent = 'sensor_agent';
    case NihCde = 'nih_cde';
    case JaxPhenome = 'jax_phenome';
    case Impc = 'impc';
    case Mnms = 'mnms';
    case GuidelinesHub = 'guidelines_hub';
}
