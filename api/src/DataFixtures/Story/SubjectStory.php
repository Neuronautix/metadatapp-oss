<?php

declare(strict_types=1);

namespace App\DataFixtures\Story;

use App\DataFixtures\Factory\SubjectFactory;
use Zenstruck\Foundry\Story;

final class SubjectStory extends Story
{
    public function build(): void
    {
        SubjectFactory::createMany(10);
    }
}
