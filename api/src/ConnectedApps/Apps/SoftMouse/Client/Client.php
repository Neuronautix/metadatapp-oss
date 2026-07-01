<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client;

use App\ConnectedApps\Apps\SoftMouse\Client\Resources\Animals;
use App\ConnectedApps\Apps\SoftMouse\Client\Resources\Cages;
use App\ConnectedApps\Apps\SoftMouse\Client\Resources\Protocols;
use App\ConnectedApps\Apps\SoftMouse\Client\Resources\Studies;
use App\ConnectedApps\Apps\SoftMouse\Client\Resources\StudyData;

/**
 * API client for SoftMouse resources.
 * Requires a valid token (e.g. stored on the User entity) upon construction.
 */
class Client implements ClientInterface
{
    // injected form service dfactor builder
    public function __construct(
        private readonly SoftMouseHttpClientInterface $softMouseHttp,
    ) {
    }

    public function animals(): Animals
    {
        return new Animals($this->softMouseHttp);
    }

    public function cages(): Cages
    {
        return new Cages($this->softMouseHttp);
    }

    public function protocols(): Protocols
    {
        return new Protocols($this->softMouseHttp);
    }

    public function studies(): Studies
    {
        return new Studies($this->softMouseHttp);
    }

    public function studyData(): StudyData
    {
        return new StudyData($this->softMouseHttp);
    }
}
