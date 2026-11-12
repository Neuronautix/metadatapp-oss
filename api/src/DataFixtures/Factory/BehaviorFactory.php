<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Behavior;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Behavior>
 */
final class BehaviorFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    private const array APPARTUSES = [
        'Open Field Test',
        'Rotarod Apparatus',
        'Treadmill for Forced Exercise',
        'Home Cage Monitoring Systems',
        'Elevated Plus Maze (EPM)',
        'Light/Dark Box',
        'Hole Board Test',
        'Marble Burying Test',
        'Forced Swim Test (FST)',
        'Tail Suspension Test (TST)',
        'Chronic Social Defeat Stress (CSDS)',
        'Sucrose Preference Test',
        'Morris Water Maze (MWM)',
        'Radial Arm Maze',
        'T-Maze/Y-Maze',
        'Novel Object Recognition Test',
        'Barnes Maze',
        'Conditioned Fear Paradigm',
        'Puzzle Boxes',
        'Three-Chamber Social Interaction Test',
        'Resident-Intruder Test',
        'Tube Dominance Test',
        'Ultrasonic Vocalization Analysis',
        'Hot Plate Test',
        'Von Frey Test',
        'Rotating Cylinder (Whisker Test)',
        'Auditory Startle Response',
        'Olfactory Habituation/Dishabituation',
        'Food Preference Test',
        'Operant Conditioning Chambers (Skinner Box)',
        'Metabolic Cages',
        'Conditioned Place Preference (CPP)',
        'Intracranial Self-Stimulation (ICSS)',
        'Operant Self-Administration Chambers',
        'Running Wheel',
        'EEG/EMG Monitoring Systems',
        'Home Cage Circadian Monitors',
        'Tail Flick Test',
        'Hargreaves Test',
        'Chronic Constriction Injury (CCI) Setup',
        'Grip Strength Meter',
        'Wire Hang Test',
        'Self-Grooming Assessment',
        'Nest Building Test',
        'Aggression Box',
        'Social Dominance Tube Test',
        'Pup Retrieval Test',
        'Nest-Building Assessment',
        'Prepulse Inhibition (PPI) Test',
        'Habitual Behavior Testing Chamber',
    ];

    protected function defaults(): array
    {
        $start = self::faker()->dateTimeBetween('-1 year', 'now');
        $end = self::faker()->dateTimeBetween($start, '+ 2 months');

        return [
            'experiment' => ExperimentFactory::randomOrCreate(),
            'startAt' => $start,
            'endAt' => $end,
            'duration' => $end ? $end->getTimestamp() - $start->getTimestamp() : null,
            'name' => 'Behavior test: ' . self::faker()->word(),
            'protocol' => self::faker()->optional()->paragraph(),
            'protocolUri' => self::faker()->optional()->url(),
            'state' => self::faker()->randomElement(['draft', 'planned', 'in_progress', 'completed', 'cancelled']),
            'account' => AccountFactory::randomOrCreate(),
            'user' => UserFactory::randomOrCreate(),
            // Behavior-specific fields
            'apparatus' => self::faker()->randomElement(self::APPARTUSES),
            'context' => self::faker()->sentence(3),
            'lighting' => self::faker()->randomFloat(1, 0, 1000), // lighting in LUX
        ];
    }

    public static function class(): string
    {
        return Behavior::class;
    }
}
