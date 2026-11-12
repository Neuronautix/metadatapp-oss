<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client;

use App\ConnectedApps\Apps\SoftMouse\Client\Resources\Animals;
use App\ConnectedApps\Apps\SoftMouse\Client\Resources\Cages;
use App\ConnectedApps\Apps\SoftMouse\Client\Resources\Protocols;
use App\ConnectedApps\Apps\SoftMouse\Client\Resources\Studies;
use App\ConnectedApps\Apps\SoftMouse\Client\Resources\StudyData;

interface ClientInterface
{
    public function animals(): Animals;

    public function cages(): Cages;

    public function protocols(): Protocols;

    public function studies(): Studies;

    public function studyData(): StudyData;
}
