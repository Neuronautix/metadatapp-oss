<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\AnimalFacilityFactory;
use App\DataFixtures\Factory\BehaviorFactory;
use App\DataFixtures\Factory\CageFactory;
use App\DataFixtures\Factory\CageHcmMeasureFactory;
use App\DataFixtures\Factory\CageHcmSinusoidMetadataFactory;
use App\DataFixtures\Factory\ConnectedAppFactory;
use App\DataFixtures\Factory\ConnectedResourceLinkFactory;
use App\DataFixtures\Factory\EnvironmentHousingFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\HomeCageMonitoringFactory;
use App\DataFixtures\Factory\ProjectFactory;
use App\DataFixtures\Factory\StrainFactory;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\SurgeryFactory;
use App\DataFixtures\Factory\TreatmentFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\VariableFactory;
use App\DataFixtures\Factory\WeightMeasurementFactory;
use App\Enum\AppCode;
use App\Enum\ExperimentType;
use App\Enum\HcmDataType;
use App\Enum\InvasivenessType;
use App\Enum\LightCycle;
use App\Enum\Sex;
use App\Enum\Species;
use App\Enum\TreatmentCategory;
use App\Enum\TreatmentRoute;
use App\Enum\TreatmentType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

use function Zenstruck\Foundry\Persistence\save;

final class DemoFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['demo'];
    }

    private const DEMO_ACCOUNT_NAME = 'Metadatapp Demo Lab';
    private const DEMO_ACCOUNT_EXTERNAL_KEY = 'metadatapp-demo-lab';
    private const DEMO_ADMIN_USER_EMAIL = 'demo.admin@metadatapp.net';
    private const DEMO_USER_EMAIL = 'demo.user@metadatapp.net';

    public function load(ObjectManager $manager): void
    {
        $account = AccountFactory::createOne([
            'name' => self::DEMO_ACCOUNT_NAME,
            'externalKey' => self::DEMO_ACCOUNT_EXTERNAL_KEY,
        ]);

        $admin = UserFactory::createOneAdmin([
            'email' => self::DEMO_ADMIN_USER_EMAIL,
            'firstName' => 'Demo',
            'lastName' => 'Admin',
            'account' => $account,
        ]);

        $user = UserFactory::createOne([
            'email' => self::DEMO_USER_EMAIL,
            'firstName' => 'Demo',
            'lastName' => 'User',
            'account' => $account,
        ]);

        $anchor = new \DateTimeImmutable('today 09:00:00');
        $at = static fn (string $modifier): \DateTime => \DateTime::createFromImmutable($anchor->modify($modifier));

        $animalFacility = AnimalFacilityFactory::createOne([
            'name' => 'Demo Rodent Facility',
            'sanitaryStatus' => 'SPF',
            'account' => $account,
        ]);

        $housingMale = EnvironmentHousingFactory::createOne([
            'room' => 'B-201',
            'temperature' => 22.0,
            'humidity' => 50.0,
            'animalFacility' => $animalFacility,
            'account' => $account,
        ]);

        $housingFemale = EnvironmentHousingFactory::createOne([
            'room' => 'B-202',
            'temperature' => 22.0,
            'humidity' => 50.0,
            'animalFacility' => $animalFacility,
            'account' => $account,
        ]);

        $housingRat = EnvironmentHousingFactory::createOne([
            'room' => 'C-101',
            'temperature' => 21.0,
            'humidity' => 55.0,
            'animalFacility' => $animalFacility,
            'account' => $account,
        ]);

        $strainC57 = StrainFactory::createOne(['name' => 'C57BL/6J']);
        $strainBALBC = StrainFactory::createOne(['name' => 'BALB/c']);
        $strainSprague = StrainFactory::createOne(['name' => 'Sprague-Dawley']);

        // ── Investigation 1: Circadian Rhythms Investigation ──────────────────
        $inv1 = ProjectFactory::createOne([
            'name' => 'Circadian Rhythms Investigation',
            'goal' => 'Characterise activity and metabolic patterns across light/dark cycles.',
            'description' => 'Multi-study investigation examining how light-cycle disruption and pharmacological interventions modulate circadian behaviour in C57BL/6J mice.',
            'startAt' => $at('-30 days'),
            'endAt' => $at('+60 days'),
            'user' => $admin,
            'account' => $account,
            'repositoryLink' => 'https://example.invalid/demo/circadian',
        ]);

        // Study 1.1 – Baseline Phenotyping (male, 12 subjects, 4 cages × 3)
        $study1_1 = ExperimentFactory::createOne([
            'name' => 'Baseline Phenotyping',
            'goal' => 'Collect baseline weight, activity, and behaviour data on naïve male mice.',
            'description' => 'Entry cohort establishing reference measurements for all downstream circadian studies.',
            'project' => $inv1,
            'user' => $admin,
            'account' => $account,
            'type' => ExperimentType::TypeA,
            'startAt' => $at('-28 days'),
            'endAt' => $at('-14 days'),
            'protocol' => 'https://example.invalid/protocols/baseline-phenotyping',
        ]);
        $hcm1_1 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study1_1,
            'system' => 'HCM-CIRCADIAN-01',
            'account' => $account,
        ]);
        $cages1_1 = $this->createCages(4, $animalFacility, $housingMale, $hcm1_1, $account, 'C1S1');
        $subjects1_1 = $this->createSubjects(12, Sex::Male, Species::Mouse, $strainC57, $cages1_1, $account, 'C1S1', $at('-56 days'));
        $this->createTreatments($study1_1, $admin, $account, $subjects1_1, $at, [
            ['Vehicle Control', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'NaCl 0.9%', 0.1, 'ml/kg', 'completed', '-27 days', 60],
            ['Melatonin 5 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IP, 'Melatonin', 5.0, 'mg/kg', 'completed', '-26 days', 60],
            ['Caffeine 20 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Caffeine', 20.0, 'mg/kg', 'completed', '-25 days', 45],
        ]);
        $this->createBehaviors($study1_1, $admin, $account, $subjects1_1, $at, [
            ['Open Field Test – Day 1', 'Open Field Test', 'standard 60-min session, 200 lux', 200.0, 'completed', '-24 days', 60],
            ['Open Field Test – Day 7', 'Open Field Test', 'standard 60-min session, 200 lux', 200.0, 'completed', '-21 days', 60],
            ['Home Cage Monitoring – 24 h', 'Home Cage Monitoring Systems', 'continuous 24-h HCM recording', 50.0, 'completed', '-23 days', 1440],
            ['Wheel Running – 7 days', 'Running Wheel', 'voluntary running, lights off 18:00', 0.0, 'completed', '-20 days', 10080],
            ['Light/Dark Box Screening', 'Light/Dark Box', 'anxiety screen, baseline', 200.0, 'completed', '-18 days', 10],
        ]);
        $this->createWeightSeries($subjects1_1, $admin, $account, $at, '-28 days', 7, 2);

        // Study 1.2 – Light/Dark Cycle Challenge (female, 16 subjects, 4 cages × 4)
        $study1_2 = ExperimentFactory::createOne([
            'name' => 'Light/Dark Cycle Challenge',
            'goal' => 'Assess behavioural and physiological responses to 12-hour phase inversion.',
            'description' => 'Female cohort exposed to reversed light cycle for 14 days with behavioural readouts.',
            'project' => $inv1,
            'user' => $admin,
            'account' => $account,
            'type' => ExperimentType::TypeA,
            'startAt' => $at('-14 days'),
            'endAt' => $at('+7 days'),
            'protocol' => 'https://example.invalid/protocols/ld-challenge',
        ]);
        $hcm1_2 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study1_2,
            'system' => 'HCM-CIRCADIAN-02',
            'account' => $account,
        ]);
        $cages1_2 = $this->createCages(4, $animalFacility, $housingFemale, $hcm1_2, $account, 'C1S2');
        $subjects1_2 = $this->createSubjects(16, Sex::Female, Species::Mouse, $strainC57, $cages1_2, $account, 'C1S2', $at('-56 days'));
        $this->createTreatments($study1_2, $admin, $account, $subjects1_2, $at, [
            ['Vehicle Control', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'NaCl 0.9%', 0.1, 'ml/kg', 'completed', '-13 days', 60],
            ['Ramelteon 0.3 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IP, 'Ramelteon', 0.3, 'mg/kg', 'in_progress', '-12 days', 60],
            ['Ramelteon 1 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IP, 'Ramelteon', 1.0, 'mg/kg', 'in_progress', '-12 days', 60],
            ['Ramelteon 3 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IP, 'Ramelteon', 3.0, 'mg/kg', 'planned', '-12 days', 60],
        ]);
        $this->createBehaviors($study1_2, $admin, $account, $subjects1_2, $at, [
            ['Open Field Test – pre-reversal', 'Open Field Test', '60-min session at ZT0', 200.0, 'completed', '-13 days', 60],
            ['Open Field Test – post-reversal D7', 'Open Field Test', '60-min session at ZT12', 200.0, 'completed', '-7 days', 60],
            ['Open Field Test – post-reversal D14', 'Open Field Test', '60-min session at ZT12', 200.0, 'in_progress', '+1 day', 60],
            ['HCM Recording – pre-reversal', 'Home Cage Monitoring Systems', '48-h baseline', 0.0, 'completed', '-14 days', 2880],
            ['HCM Recording – post-reversal', 'Home Cage Monitoring Systems', '48-h post phase-shift', 0.0, 'in_progress', '-7 days', 2880],
            ['Sucrose Preference Test', 'Sucrose Preference Test', '24-h two-bottle choice', 0.0, 'completed', '-9 days', 1440],
        ]);
        $this->createWeightSeries($subjects1_2, $admin, $account, $at, '-14 days', 7, 3);

        // Study 1.3 – Rest-Activity Monitoring (male, 14 subjects, 4 cages × 3-4)
        $study1_3 = ExperimentFactory::createOne([
            'name' => 'Rest-Activity Monitoring',
            'goal' => 'Quantify rest-activity fragmentation under constant darkness.',
            'description' => 'Male mice maintained in DD conditions for 21 days with continuous HCM recording.',
            'project' => $inv1,
            'user' => $admin,
            'account' => $account,
            'type' => ExperimentType::TypeB,
            'startAt' => $at('+10 days'),
            'endAt' => $at('+31 days'),
            'protocol' => 'https://example.invalid/protocols/rest-activity',
        ]);
        $hcm1_3 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study1_3,
            'system' => 'HCM-CIRCADIAN-03',
            'account' => $account,
        ]);
        $cages1_3 = $this->createCages(4, $animalFacility, $housingMale, $hcm1_3, $account, 'C1S3');
        $subjects1_3 = $this->createSubjects(14, Sex::Male, Species::Mouse, $strainC57, $cages1_3, $account, 'C1S3', $at('-84 days'));
        $this->createTreatments($study1_3, $admin, $account, $subjects1_3, $at, [
            ['Vehicle Control', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'NaCl 0.9%', 0.1, 'ml/kg', 'planned', '+10 days', 60],
            ['Lithium 300 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::Gavage, 'Lithium chloride', 300.0, 'mg/kg', 'planned', '+10 days', 43200],
        ]);
        $this->createBehaviors($study1_3, $admin, $account, $subjects1_3, $at, [
            ['HCM Recording Week 1', 'Home Cage Monitoring Systems', 'DD condition, week 1', 0.0, 'planned', '+10 days', 10080],
            ['HCM Recording Week 2', 'Home Cage Monitoring Systems', 'DD condition, week 2', 0.0, 'planned', '+17 days', 10080],
            ['HCM Recording Week 3', 'Home Cage Monitoring Systems', 'DD condition, week 3', 0.0, 'planned', '+24 days', 10080],
            ['Wheel Running – DD', 'Running Wheel', 'free-running period estimation', 0.0, 'planned', '+10 days', 21600],
            ['EEG/EMG Recording', 'EEG/EMG Monitoring Systems', '48-h polysomnography', 0.0, 'planned', '+20 days', 2880],
        ]);
        $this->createWeightSeries($subjects1_3, $admin, $account, $at, '-7 days', 7, 1);
        $this->createHcmBenchmarkSystems($inv1, $admin, $account, $animalFacility, $housingMale, $strainC57, $at);

        // ── Investigation 2: Stress Resilience Cohort ─────────────────────────
        $inv2 = ProjectFactory::createOne([
            'name' => 'Stress Resilience Cohort',
            'goal' => 'Identify behavioural and molecular markers of stress resilience vs. susceptibility.',
            'description' => 'Two-study cohort comparing acute and chronic stress responses using CORT and CSDS paradigms in BALB/c mice.',
            'startAt' => $at('-21 days'),
            'endAt' => $at('+28 days'),
            'user' => $user,
            'account' => $account,
            'repositoryLink' => 'https://example.invalid/demo/stress',
        ]);

        // Study 2.1 – Acute Stress Protocol (female, 15 subjects, 5 cages × 3)
        $study2_1 = ExperimentFactory::createOne([
            'name' => 'Acute Stress Protocol',
            'goal' => 'Profile HPA-axis activation and behavioural sequelae 24 h after acute restraint.',
            'description' => 'Female BALB/c mice, 30-min restraint stress, behavioural and corticosterone readouts.',
            'project' => $inv2,
            'user' => $user,
            'account' => $account,
            'type' => ExperimentType::TypeA,
            'startAt' => $at('-18 days'),
            'endAt' => $at('-4 days'),
            'protocol' => 'https://example.invalid/protocols/acute-stress',
        ]);
        $hcm2_1 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study2_1,
            'system' => 'HCM-STRESS-01',
            'account' => $account,
        ]);
        $cages2_1 = $this->createCages(5, $animalFacility, $housingFemale, $hcm2_1, $account, 'C2S1');
        $subjects2_1 = $this->createSubjects(15, Sex::Female, Species::Mouse, $strainBALBC, $cages2_1, $account, 'C2S1', $at('-70 days'));
        $this->createTreatments($study2_1, $user, $account, $subjects2_1, $at, [
            ['Restraint Stress 30 min', TreatmentType::Environmental, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Restraint', 0.0, 'min', 'completed', '-17 days', 30],
            ['Corticosterone 20 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Corticosterone', 20.0, 'mg/kg', 'completed', '-16 days', 30],
            ['Mifepristone 200 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Mifepristone', 200.0, 'mg/kg', 'completed', '-16 days', 30],
        ]);
        $this->createBehaviors($study2_1, $user, $account, $subjects2_1, $at, [
            ['Open Field Test', 'Open Field Test', '5-min session 24 h post-stress', 200.0, 'completed', '-16 days', 5],
            ['Elevated Plus Maze', 'Elevated Plus Maze (EPM)', '5-min session', 100.0, 'completed', '-15 days', 5],
            ['Forced Swim Test', 'Forced Swim Test (FST)', '6-min session', 200.0, 'completed', '-14 days', 6],
        ]);
        $this->createWeightSeries($subjects2_1, $user, $account, $at, '-18 days', 7, 2);

        // Study 2.2 – Post-Stress Recovery (male, 12 subjects, 4 cages × 3)
        $study2_2 = ExperimentFactory::createOne([
            'name' => 'Post-Stress Recovery',
            'goal' => 'Track behavioural recovery after chronic social defeat stress over 21 days.',
            'description' => 'Male BALB/c mice, 10-day CSDS paradigm, followed by 21-day recovery with fluoxetine treatment.',
            'project' => $inv2,
            'user' => $user,
            'account' => $account,
            'type' => ExperimentType::TypeB,
            'startAt' => $at('-5 days'),
            'endAt' => $at('+27 days'),
            'protocol' => 'https://example.invalid/protocols/post-stress-recovery',
        ]);
        $hcm2_2 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study2_2,
            'system' => 'HCM-STRESS-02',
            'account' => $account,
        ]);
        $cages2_2 = $this->createCages(4, $animalFacility, $housingMale, $hcm2_2, $account, 'C2S2');
        $subjects2_2 = $this->createSubjects(12, Sex::Male, Species::Mouse, $strainBALBC, $cages2_2, $account, 'C2S2', $at('-70 days'));
        $this->createTreatments($study2_2, $user, $account, $subjects2_2, $at, [
            ['CSDS – 10 days', TreatmentType::Environmental, TreatmentCategory::CHRONIC, TreatmentRoute::IP, 'Social defeat', 0.0, 'sessions', 'completed', '-5 days', 14400],
            ['Fluoxetine 10 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::Gavage, 'Fluoxetine', 10.0, 'mg/kg', 'in_progress', '+5 days', 302400],
            ['Vehicle', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::Gavage, 'Vehicle', 0.0, 'ml/kg', 'in_progress', '+5 days', 302400],
            ['Imipramine 20 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IP, 'Imipramine', 20.0, 'mg/kg', 'planned', '+5 days', 302400],
        ]);
        $this->createBehaviors($study2_2, $user, $account, $subjects2_2, $at, [
            ['Social Interaction Test – post CSDS', 'Three-Chamber Social Interaction Test', 'defeat susceptibility screening', 200.0, 'completed', '+5 days', 10],
            ['Sucrose Preference – week 1', 'Sucrose Preference Test', 'anhedonia screen', 0.0, 'in_progress', '+12 days', 1440],
            ['Sucrose Preference – week 3', 'Sucrose Preference Test', 'recovery readout', 0.0, 'planned', '+26 days', 1440],
            ['Open Field Test – week 3', 'Open Field Test', '10-min session', 200.0, 'planned', '+26 days', 10],
            ['Resident–Intruder Test', 'Resident-Intruder Test', 'aggression / social re-integration', 200.0, 'planned', '+25 days', 10],
        ]);
        $this->createSurgeries($study2_2, $user, $account, $subjects2_2, $at, [
            ['Cannula implantation – ICV', InvasivenessType::Surgery, 'completed', '-4 days', 60],
            ['Tissue biopsy – hippocampus', InvasivenessType::Biopsy, 'planned', '+27 days', 30],
        ]);
        $this->createWeightSeries($subjects2_2, $user, $account, $at, '-5 days', 7, 4);

        // ── Investigation 3: Cognitive Aging Study ────────────────────────────
        $inv3 = ProjectFactory::createOne([
            'name' => 'Cognitive Aging Study',
            'goal' => 'Map cognitive decline trajectories and test nootropic interventions across age groups.',
            'description' => 'Four-study design comparing young, middle-aged, and aged C57BL/6J cohorts on spatial and recognition memory tasks.',
            'startAt' => $at('-90 days'),
            'endAt' => $at('+120 days'),
            'user' => $admin,
            'account' => $account,
            'repositoryLink' => 'https://example.invalid/demo/cognitive-aging',
        ]);

        // Study 3.1 – Young Adult Baseline (male, 18 subjects, 6 cages × 3)
        $study3_1 = ExperimentFactory::createOne([
            'name' => 'Young Adult Baseline',
            'goal' => 'Establish cognitive performance benchmarks in 3-month-old male mice.',
            'description' => 'Reference cohort for Morris Water Maze, Novel Object Recognition, and Barnes Maze performance.',
            'project' => $inv3,
            'user' => $admin,
            'account' => $account,
            'type' => ExperimentType::TypeA,
            'startAt' => $at('-88 days'),
            'endAt' => $at('-60 days'),
            'protocol' => 'https://example.invalid/protocols/young-baseline',
        ]);
        $hcm3_1 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study3_1,
            'system' => 'HCM-AGING-01',
            'account' => $account,
        ]);
        $cages3_1 = $this->createCages(6, $animalFacility, $housingMale, $hcm3_1, $account, 'C3S1');
        $subjects3_1 = $this->createSubjects(18, Sex::Male, Species::Mouse, $strainC57, $cages3_1, $account, 'C3S1', $at('-180 days'));
        $this->createTreatments($study3_1, $admin, $account, $subjects3_1, $at, [
            ['Vehicle', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'NaCl 0.9%', 0.1, 'ml/kg', 'completed', '-87 days', 60],
            ['Donepezil 1 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Donepezil', 1.0, 'mg/kg', 'completed', '-87 days', 60],
            ['Memantine 5 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Memantine', 5.0, 'mg/kg', 'completed', '-87 days', 60],
            ['Memantine 20 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Memantine', 20.0, 'mg/kg', 'completed', '-87 days', 60],
        ]);
        $this->createBehaviors($study3_1, $admin, $account, $subjects3_1, $at, [
            ['Morris Water Maze – hidden platform', 'Morris Water Maze (MWM)', '5 days acquisition, 200 lux', 200.0, 'completed', '-85 days', 300],
            ['Morris Water Maze – probe trial', 'Morris Water Maze (MWM)', 'probe trial day 6', 200.0, 'completed', '-80 days', 60],
            ['Novel Object Recognition – 24 h', 'Novel Object Recognition Test', '10-min exploration, 24-h delay', 200.0, 'completed', '-78 days', 10],
            ['Barnes Maze – acquisition', 'Barnes Maze', '4-day acquisition', 600.0, 'completed', '-75 days', 240],
            ['Barnes Maze – probe', 'Barnes Maze', 'probe trial day 5', 600.0, 'completed', '-71 days', 60],
            ['Open Field Test', 'Open Field Test', 'locomotion control', 200.0, 'completed', '-69 days', 60],
            ['Nesting behaviour', 'Nest Building Test', 'overnight nest score', 50.0, 'completed', '-68 days', 480],
            ['Grip Strength', 'Grip Strength Meter', '3 trials per animal', 200.0, 'completed', '-67 days', 5],
        ]);
        $this->createWeightSeries($subjects3_1, $admin, $account, $at, '-88 days', 14, 2);

        // Study 3.2 – Middle-Aged Cohort (female, 15 subjects, 5 cages × 3)
        $study3_2 = ExperimentFactory::createOne([
            'name' => 'Middle-Aged Cohort',
            'goal' => 'Quantify early cognitive decline in 12-month-old female mice.',
            'description' => 'Female cohort matched on body weight; spatial and non-spatial memory compared to young baseline.',
            'project' => $inv3,
            'user' => $admin,
            'account' => $account,
            'type' => ExperimentType::TypeA,
            'startAt' => $at('-60 days'),
            'endAt' => $at('-30 days'),
            'protocol' => 'https://example.invalid/protocols/middle-aged',
        ]);
        $hcm3_2 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study3_2,
            'system' => 'HCM-AGING-02',
            'account' => $account,
        ]);
        $cages3_2 = $this->createCages(5, $animalFacility, $housingFemale, $hcm3_2, $account, 'C3S2');
        $subjects3_2 = $this->createSubjects(15, Sex::Female, Species::Mouse, $strainC57, $cages3_2, $account, 'C3S2', $at('-420 days'));
        $this->createTreatments($study3_2, $admin, $account, $subjects3_2, $at, [
            ['Vehicle', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'NaCl 0.9%', 0.1, 'ml/kg', 'completed', '-59 days', 60],
            ['Donepezil 1 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Donepezil', 1.0, 'mg/kg', 'completed', '-59 days', 60],
            ['Rapamycin 2 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IP, 'Rapamycin', 2.0, 'mg/kg', 'completed', '-59 days', 60],
        ]);
        $this->createBehaviors($study3_2, $admin, $account, $subjects3_2, $at, [
            ['Morris Water Maze – acquisition', 'Morris Water Maze (MWM)', '5-day cued + hidden', 200.0, 'completed', '-58 days', 300],
            ['Morris Water Maze – probe', 'Morris Water Maze (MWM)', 'probe trial', 200.0, 'completed', '-53 days', 60],
            ['Novel Object Recognition', 'Novel Object Recognition Test', '1-h and 24-h retention', 200.0, 'completed', '-50 days', 10],
            ['Y-Maze spontaneous alternation', 'T-Maze/Y-Maze', 'spatial working memory', 200.0, 'completed', '-48 days', 8],
            ['Open Field Test', 'Open Field Test', 'locomotion control', 200.0, 'completed', '-46 days', 60],
        ]);
        $this->createWeightSeries($subjects3_2, $admin, $account, $at, '-60 days', 14, 2);

        // Study 3.3 – Aged Cohort Assessment (male, 12 subjects, 4 cages × 3)
        $study3_3 = ExperimentFactory::createOne([
            'name' => 'Aged Cohort Assessment',
            'goal' => 'Document severe cognitive deficit profile in 22-month-old male mice.',
            'description' => 'Aged cohort with full behavioural battery and post-mortem hippocampal tissue collection.',
            'project' => $inv3,
            'user' => $admin,
            'account' => $account,
            'type' => ExperimentType::TypeA,
            'startAt' => $at('-30 days'),
            'endAt' => $at('+5 days'),
            'protocol' => 'https://example.invalid/protocols/aged-cohort',
        ]);
        $hcm3_3 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study3_3,
            'system' => 'HCM-AGING-03',
            'account' => $account,
        ]);
        $cages3_3 = $this->createCages(4, $animalFacility, $housingMale, $hcm3_3, $account, 'C3S3');
        $subjects3_3 = $this->createSubjects(12, Sex::Male, Species::Mouse, $strainC57, $cages3_3, $account, 'C3S3', $at('-660 days'));
        $this->createTreatments($study3_3, $admin, $account, $subjects3_3, $at, [
            ['Vehicle', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'NaCl 0.9%', 0.1, 'ml/kg', 'completed', '-29 days', 60],
            ['Donepezil 3 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IP, 'Donepezil', 3.0, 'mg/kg', 'completed', '-29 days', 60],
            ['Nicotinamide riboside 400 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::Gavage, 'NR', 400.0, 'mg/kg', 'in_progress', '-20 days', 43200],
        ]);
        $this->createBehaviors($study3_3, $admin, $account, $subjects3_3, $at, [
            ['Morris Water Maze – acquisition', 'Morris Water Maze (MWM)', '6-day acquisition', 200.0, 'completed', '-28 days', 300],
            ['Morris Water Maze – probe', 'Morris Water Maze (MWM)', 'probe trial', 200.0, 'completed', '-22 days', 60],
            ['Novel Object Recognition', 'Novel Object Recognition Test', '1-h delay', 200.0, 'completed', '-20 days', 10],
            ['Barnes Maze', 'Barnes Maze', '6-day acquisition + probe', 600.0, 'completed', '-18 days', 300],
            ['Open Field Test', 'Open Field Test', 'locomotion and anxiety', 200.0, 'completed', '-10 days', 60],
            ['Grip Strength', 'Grip Strength Meter', 'motor function control', 200.0, 'completed', '-8 days', 5],
            ['Wire Hang Test', 'Wire Hang Test', 'forelimb strength', 200.0, 'completed', '-8 days', 5],
        ]);
        $this->createSurgeries($study3_3, $admin, $account, $subjects3_3, $at, [
            ['Hippocampal biopsy', InvasivenessType::Biopsy, 'in_progress', '+4 days', 60],
            ['Perfusion fixation', InvasivenessType::Surgery, 'planned', '+5 days', 30],
            ['CSF collection', InvasivenessType::Injection, 'in_progress', '+3 days', 20],
        ]);
        $this->createWeightSeries($subjects3_3, $admin, $account, $at, '-30 days', 7, 4);

        // Study 3.4 – Pharmacological Intervention (female, 20 subjects, 5 cages × 4)
        $study3_4 = ExperimentFactory::createOne([
            'name' => 'Pharmacological Intervention',
            'goal' => 'Test three nootropic candidates for reversal of age-related spatial memory deficit.',
            'description' => 'Aged female mice, 4-arm dose-response design with cognition-enhancing compounds.',
            'project' => $inv3,
            'user' => $admin,
            'account' => $account,
            'type' => ExperimentType::TypeC,
            'startAt' => $at('+10 days'),
            'endAt' => $at('+90 days'),
            'protocol' => 'https://example.invalid/protocols/pharma-intervention',
        ]);
        $hcm3_4 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study3_4,
            'system' => 'HCM-AGING-04',
            'account' => $account,
        ]);
        $cages3_4 = $this->createCages(5, $animalFacility, $housingFemale, $hcm3_4, $account, 'C3S4');
        $subjects3_4 = $this->createSubjects(20, Sex::Female, Species::Mouse, $strainC57, $cages3_4, $account, 'C3S4', $at('-660 days'));
        $this->createTreatments($study3_4, $admin, $account, $subjects3_4, $at, [
            ['Vehicle control', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IP, 'NaCl 0.9%', 0.1, 'ml/kg', 'planned', '+10 days', 60],
            ['Compound A low dose', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IP, 'Compound A', 1.0, 'mg/kg', 'planned', '+10 days', 60],
            ['Compound A high dose', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IP, 'Compound A', 10.0, 'mg/kg', 'planned', '+10 days', 60],
            ['Compound B', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::Gavage, 'Compound B', 30.0, 'mg/kg', 'planned', '+10 days', 60],
            ['Compound C', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::Gavage, 'Compound C', 50.0, 'mg/kg', 'planned', '+10 days', 60],
        ]);
        $this->createBehaviors($study3_4, $admin, $account, $subjects3_4, $at, [
            ['Open Field – week 2', 'Open Field Test', 'locomotion before MWM', 200.0, 'planned', '+20 days', 60],
            ['MWM – acquisition', 'Morris Water Maze (MWM)', '5-day training', 200.0, 'planned', '+25 days', 300],
            ['MWM – probe 24h', 'Morris Water Maze (MWM)', 'probe trial', 200.0, 'planned', '+30 days', 60],
            ['MWM – probe 7d', 'Morris Water Maze (MWM)', 'long-term retention probe', 200.0, 'planned', '+37 days', 60],
            ['Y-Maze', 'T-Maze/Y-Maze', 'working memory week 6', 200.0, 'planned', '+52 days', 8],
            ['Novel Object Recognition', 'Novel Object Recognition Test', '1-h and 24-h', 200.0, 'planned', '+55 days', 10],
            ['Barnes Maze – week 10', 'Barnes Maze', '4-day retraining', 600.0, 'planned', '+80 days', 240],
            ['Grip Strength – week 12', 'Grip Strength Meter', 'motor safety readout', 200.0, 'planned', '+88 days', 5],
            ['Body composition scan', 'Metabolic Cages', 'DEXA equivalent, 2-h session', 50.0, 'planned', '+89 days', 120],
        ]);
        $this->createWeightSeries($subjects3_4, $admin, $account, $at, '+5 days', 14, 1);

        // ── Investigation 4: Metabolic Disease Model ──────────────────────────
        $inv4 = ProjectFactory::createOne([
            'name' => 'Metabolic Disease Model',
            'goal' => 'Characterise diet-induced obesity and evaluate pharmacological reversal of insulin resistance.',
            'description' => 'Three-study programme using high-fat diet C57BL/6J mice to model type-2 diabetes and test GLP-1 agonists.',
            'startAt' => $at('-45 days'),
            'endAt' => $at('+90 days'),
            'user' => $user,
            'account' => $account,
            'repositoryLink' => 'https://example.invalid/demo/metabolic',
        ]);

        // Study 4.1 – High-Fat Diet Baseline (male, 16 subjects, 4 cages × 4)
        $study4_1 = ExperimentFactory::createOne([
            'name' => 'High-Fat Diet Baseline',
            'goal' => 'Confirm diet-induced obesity phenotype after 8 weeks on 60% HFD.',
            'description' => 'Male mice randomised to standard chow or HFD; weekly weight and metabolic profiling.',
            'project' => $inv4,
            'user' => $user,
            'account' => $account,
            'type' => ExperimentType::TypeA,
            'startAt' => $at('-45 days'),
            'endAt' => $at('-10 days'),
            'protocol' => 'https://example.invalid/protocols/hfd-baseline',
        ]);
        $hcm4_1 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study4_1,
            'system' => 'HCM-METABOLIC-01',
            'account' => $account,
        ]);
        $cages4_1 = $this->createCages(4, $animalFacility, $housingMale, $hcm4_1, $account, 'C4S1');
        $subjects4_1 = $this->createSubjects(16, Sex::Male, Species::Mouse, $strainC57, $cages4_1, $account, 'C4S1', $at('-168 days'));
        $this->createTreatments($study4_1, $user, $account, $subjects4_1, $at, [
            ['Standard chow (10% kcal fat)', TreatmentType::InFood, TreatmentCategory::CHRONIC, TreatmentRoute::Gavage, 'Standard chow', 10.0, '% kcal fat', 'completed', '-45 days', 64800],
            ['High-fat diet (60% kcal fat)', TreatmentType::InFood, TreatmentCategory::CHRONIC, TreatmentRoute::Gavage, 'HFD 60%', 60.0, '% kcal fat', 'completed', '-45 days', 64800],
            ['Glucose tolerance test', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'D-Glucose', 2.0, 'g/kg', 'completed', '-11 days', 120],
            ['Insulin tolerance test', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Regular insulin', 0.75, 'IU/kg', 'completed', '-10 days', 90],
        ]);
        $this->createBehaviors($study4_1, $user, $account, $subjects4_1, $at, [
            ['Metabolic cage – week 4', 'Metabolic Cages', 'VO2, VCO2, RER, 24 h', 50.0, 'completed', '-17 days', 1440],
            ['Metabolic cage – week 8', 'Metabolic Cages', 'VO2, VCO2, RER, 24 h', 50.0, 'completed', '-11 days', 1440],
            ['Open Field Test', 'Open Field Test', 'locomotion control', 200.0, 'completed', '-12 days', 60],
            ['Home Cage Feeding Monitor', 'Home Cage Monitoring Systems', 'food intake 7-day recording', 0.0, 'completed', '-20 days', 10080],
            ['Grip Strength', 'Grip Strength Meter', 'muscle strength, pre-treatment', 200.0, 'completed', '-13 days', 5],
        ]);
        $this->createWeightSeries($subjects4_1, $user, $account, $at, '-45 days', 7, 6);

        // Study 4.2 – Insulin Resistance Assessment (female, 12 subjects, 3 cages × 4)
        $study4_2 = ExperimentFactory::createOne([
            'name' => 'Insulin Resistance Assessment',
            'goal' => 'Characterise the insulin resistance profile in obese female mice with tissue biopsy.',
            'description' => 'Female HFD-fed mice, metabolic phenotyping plus liver and adipose tissue collection.',
            'project' => $inv4,
            'user' => $user,
            'account' => $account,
            'type' => ExperimentType::TypeA,
            'startAt' => $at('-10 days'),
            'endAt' => $at('+14 days'),
            'protocol' => 'https://example.invalid/protocols/insulin-resistance',
        ]);
        $hcm4_2 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study4_2,
            'system' => 'HCM-METABOLIC-02',
            'account' => $account,
        ]);
        $cages4_2 = $this->createCages(3, $animalFacility, $housingFemale, $hcm4_2, $account, 'C4S2');
        $subjects4_2 = $this->createSubjects(12, Sex::Female, Species::Mouse, $strainC57, $cages4_2, $account, 'C4S2', $at('-168 days'));
        $this->createTreatments($study4_2, $user, $account, $subjects4_2, $at, [
            ['HFD continuation', TreatmentType::InFood, TreatmentCategory::CHRONIC, TreatmentRoute::Gavage, 'HFD 60%', 60.0, '% kcal fat', 'in_progress', '-10 days', 34560],
            ['Glucose tolerance test', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'D-Glucose', 2.0, 'g/kg', 'completed', '-9 days', 120],
            ['Insulin tolerance test', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Regular insulin', 0.5, 'IU/kg', 'in_progress', '+3 days', 90],
        ]);
        $this->createBehaviors($study4_2, $user, $account, $subjects4_2, $at, [
            ['Metabolic cage 48 h', 'Metabolic Cages', 'energy balance assessment', 50.0, 'in_progress', '+7 days', 2880],
            ['Home Cage Monitor', 'Home Cage Monitoring Systems', '24-h food intake', 0.0, 'completed', '-5 days', 1440],
            ['Open Field Test', 'Open Field Test', 'locomotion screen', 200.0, 'completed', '-3 days', 60],
            ['Body composition DEXA', 'Metabolic Cages', 'lean vs fat mass', 50.0, 'in_progress', '+13 days', 30],
        ]);
        $this->createSurgeries($study4_2, $user, $account, $subjects4_2, $at, [
            ['Liver biopsy', InvasivenessType::Biopsy, 'planned', '+14 days', 30],
            ['Epididymal fat pad excision', InvasivenessType::Biopsy, 'planned', '+14 days', 30],
            ['Cardiac puncture – terminal bleed', InvasivenessType::Surgery, 'planned', '+14 days', 10],
        ]);
        $this->createWeightSeries($subjects4_2, $user, $account, $at, '-10 days', 7, 2);

        // Study 4.3 – Pharmacological Intervention (male, 20 subjects, 5 cages × 4)
        $study4_3 = ExperimentFactory::createOne([
            'name' => 'GLP-1 Agonist Dose-Response',
            'goal' => 'Evaluate semaglutide dose-response on body weight and glycaemic control in DIO mice.',
            'description' => 'Male DIO mice, four-week treatment with semaglutide at three doses vs. vehicle.',
            'project' => $inv4,
            'user' => $user,
            'account' => $account,
            'type' => ExperimentType::TypeC,
            'startAt' => $at('+15 days'),
            'endAt' => $at('+90 days'),
            'protocol' => 'https://example.invalid/protocols/glp1-dose-response',
        ]);
        $hcm4_3 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study4_3,
            'system' => 'HCM-METABOLIC-03',
            'account' => $account,
        ]);
        $cages4_3 = $this->createCages(5, $animalFacility, $housingMale, $hcm4_3, $account, 'C4S3');
        $subjects4_3 = $this->createSubjects(20, Sex::Male, Species::Mouse, $strainC57, $cages4_3, $account, 'C4S3', $at('-168 days'));
        $this->createTreatments($study4_3, $user, $account, $subjects4_3, $at, [
            ['Vehicle SC', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IV, 'NaCl 0.9%', 0.1, 'ml/kg', 'planned', '+15 days', 30],
            ['Semaglutide 0.03 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IV, 'Semaglutide', 0.03, 'mg/kg', 'planned', '+15 days', 30],
            ['Semaglutide 0.1 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IV, 'Semaglutide', 0.1, 'mg/kg', 'planned', '+15 days', 30],
            ['Semaglutide 0.3 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IV, 'Semaglutide', 0.3, 'mg/kg', 'planned', '+15 days', 30],
            ['Liraglutide 0.2 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::CHRONIC, TreatmentRoute::IV, 'Liraglutide', 0.2, 'mg/kg', 'planned', '+15 days', 30],
        ]);
        $this->createBehaviors($study4_3, $user, $account, $subjects4_3, $at, [
            ['Metabolic cage – week 2', 'Metabolic Cages', '24-h energy balance', 50.0, 'planned', '+29 days', 1440],
            ['GTT – week 4', 'Metabolic Cages', 'glucose tolerance', 50.0, 'planned', '+43 days', 120],
            ['Food intake monitoring', 'Home Cage Monitoring Systems', '7-day continuous', 0.0, 'planned', '+15 days', 10080],
            ['Open Field Test', 'Open Field Test', 'locomotion safety', 200.0, 'planned', '+44 days', 60],
            ['Metabolic cage – week 8', 'Metabolic Cages', '24-h energy balance', 50.0, 'planned', '+71 days', 1440],
            ['GTT – week 8', 'Metabolic Cages', 'glucose tolerance endpoint', 50.0, 'planned', '+85 days', 120],
            ['Body composition – endpoint', 'Metabolic Cages', 'DEXA', 50.0, 'planned', '+88 days', 30],
            ['Grip Strength', 'Grip Strength Meter', 'muscle safety', 200.0, 'planned', '+89 days', 5],
        ]);
        $this->createSurgeries($study4_3, $user, $account, $subjects4_3, $at, [
            ['Osmotic pump implantation', InvasivenessType::Surgery, 'planned', '+15 days', 45],
            ['Pump explantation', InvasivenessType::Surgery, 'planned', '+43 days', 30],
            ['Terminal cardiac puncture', InvasivenessType::Surgery, 'planned', '+90 days', 10],
            ['Pancreas biopsy', InvasivenessType::Biopsy, 'planned', '+90 days', 15],
            ['Liver biopsy', InvasivenessType::Biopsy, 'planned', '+90 days', 15],
        ]);
        $this->createWeightSeries($subjects4_3, $user, $account, $at, '+10 days', 7, 1);

        // ── Investigation 5: Neuropathic Pain Model ───────────────────────────
        $inv5 = ProjectFactory::createOne([
            'name' => 'Neuropathic Pain Model',
            'goal' => 'Validate analgesic efficacy of novel sodium-channel blockers in Sprague-Dawley rats.',
            'description' => 'Two-study programme using CCI and SNI peripheral nerve injury models in rats with quantitative sensory testing.',
            'startAt' => $at('-14 days'),
            'endAt' => $at('+56 days'),
            'user' => $admin,
            'account' => $account,
            'repositoryLink' => 'https://example.invalid/demo/pain',
        ]);

        // Study 5.1 – Sciatic Nerve Injury Model (male rats, 14 subjects, 4 cages × 3-4)
        $study5_1 = ExperimentFactory::createOne([
            'name' => 'Sciatic Nerve CCI Model',
            'goal' => 'Establish stable allodynia and hyperalgesia after chronic constriction injury.',
            'description' => 'Male Sprague-Dawley rats, CCI surgery, 28-day establishment period with weekly sensory testing.',
            'project' => $inv5,
            'user' => $admin,
            'account' => $account,
            'type' => ExperimentType::TypeB,
            'startAt' => $at('-14 days'),
            'endAt' => $at('+21 days'),
            'protocol' => 'https://example.invalid/protocols/cci-model',
        ]);
        $hcm5_1 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study5_1,
            'system' => 'HCM-PAIN-01',
            'account' => $account,
        ]);
        $cages5_1 = $this->createCages(4, $animalFacility, $housingRat, $hcm5_1, $account, 'C5S1');
        $subjects5_1 = $this->createSubjects(14, Sex::Male, Species::Rat, $strainSprague, $cages5_1, $account, 'C5S1', $at('-84 days'));
        $this->createTreatments($study5_1, $admin, $account, $subjects5_1, $at, [
            ['Vehicle SC', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IV, 'NaCl 0.9%', 0.1, 'ml/kg', 'completed', '-12 days', 30],
            ['Gabapentin 100 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Gabapentin', 100.0, 'mg/kg', 'completed', '-7 days', 30],
            ['Morphine 3 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Morphine', 3.0, 'mg/kg', 'completed', '+7 days', 30],
            ['Compound Nav1.7-blocker', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Compound X', 10.0, 'mg/kg', 'in_progress', '+14 days', 30],
        ]);
        $this->createBehaviors($study5_1, $admin, $account, $subjects5_1, $at, [
            ['Von Frey – pre-surgery', 'Von Frey Test', 'mechanical allodynia baseline', 200.0, 'completed', '-14 days', 20],
            ['Von Frey – D7', 'Von Frey Test', 'allodynia D7 post-CCI', 200.0, 'completed', '-7 days', 20],
            ['Von Frey – D14', 'Von Frey Test', 'allodynia D14 post-CCI', 200.0, 'completed', '0 days', 20],
            ['Von Frey – D21', 'Von Frey Test', 'allodynia D21 post-CCI', 200.0, 'in_progress', '+7 days', 20],
            ['Hargreaves Test – D14', 'Hargreaves Test', 'thermal hyperalgesia D14', 200.0, 'completed', '0 days', 20],
            ['Open Field Test', 'Open Field Test', 'locomotion control', 200.0, 'completed', '-1 day', 30],
            ['Auditory Startle Response', 'Auditory Startle Response', 'sensitisation screen', 200.0, 'completed', '-1 day', 20],
        ]);
        $this->createSurgeries($study5_1, $admin, $account, $subjects5_1, $at, [
            ['CCI surgery – sciatic nerve', InvasivenessType::Surgery, 'completed', '-13 days', 45],
            ['Cannula implantation – intrathecal', InvasivenessType::Surgery, 'completed', '-13 days', 60],
            ['Post-mortem DRG extraction', InvasivenessType::Biopsy, 'planned', '+21 days', 30],
            ['Spinal cord sampling', InvasivenessType::Biopsy, 'planned', '+21 days', 20],
        ]);
        $this->createWeightSeries($subjects5_1, $admin, $account, $at, '-14 days', 7, 4);

        // Study 5.2 – Analgesic Drug Screen (female rats, 18 subjects, 5 cages × 3-4)
        $study5_2 = ExperimentFactory::createOne([
            'name' => 'Analgesic Drug Screen',
            'goal' => 'Dose-response characterisation of three Nav1.7 inhibitors in female SNI rats.',
            'description' => 'Female Sprague-Dawley rats, spared nerve injury model, 4-week multi-compound analgesic screen.',
            'project' => $inv5,
            'user' => $admin,
            'account' => $account,
            'type' => ExperimentType::TypeC,
            'startAt' => $at('+22 days'),
            'endAt' => $at('+56 days'),
            'protocol' => 'https://example.invalid/protocols/analgesic-screen',
        ]);
        $hcm5_2 = HomeCageMonitoringFactory::createOne([
            'experiment' => $study5_2,
            'system' => 'HCM-PAIN-02',
            'account' => $account,
        ]);
        $cages5_2 = $this->createCages(5, $animalFacility, $housingRat, $hcm5_2, $account, 'C5S2');
        $subjects5_2 = $this->createSubjects(18, Sex::Female, Species::Rat, $strainSprague, $cages5_2, $account, 'C5S2', $at('-84 days'));
        $this->createTreatments($study5_2, $admin, $account, $subjects5_2, $at, [
            ['Vehicle', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'NaCl 0.9%', 0.1, 'ml/kg', 'planned', '+22 days', 30],
            ['Compound X 3 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Compound X', 3.0, 'mg/kg', 'planned', '+22 days', 30],
            ['Compound X 10 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Compound X', 10.0, 'mg/kg', 'planned', '+22 days', 30],
            ['Compound X 30 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Compound X', 30.0, 'mg/kg', 'planned', '+22 days', 30],
            ['Positive control – Gabapentin 100 mg/kg', TreatmentType::Pharmacological, TreatmentCategory::ACUTE, TreatmentRoute::IP, 'Gabapentin', 100.0, 'mg/kg', 'planned', '+22 days', 30],
        ]);
        $this->createBehaviors($study5_2, $admin, $account, $subjects5_2, $at, [
            ['Von Frey – baseline', 'Von Frey Test', 'pre-surgery', 200.0, 'planned', '+22 days', 20],
            ['Von Frey – D14 post-SNI', 'Von Frey Test', 'allodynia confirmation', 200.0, 'planned', '+36 days', 20],
            ['Hargreaves – D14', 'Hargreaves Test', 'thermal hyperalgesia', 200.0, 'planned', '+36 days', 20],
            ['Von Frey – drug challenge D1', 'Von Frey Test', 'peak drug effect 1 h', 200.0, 'planned', '+42 days', 20],
            ['Von Frey – drug challenge D7', 'Von Frey Test', 'repeated-dose effect', 200.0, 'planned', '+49 days', 20],
            ['Open Field Test – safety', 'Open Field Test', 'motor side-effect screen', 200.0, 'planned', '+43 days', 30],
            ['Rotarod – motor function', 'Rotarod Apparatus', 'accelerating protocol', 200.0, 'planned', '+43 days', 20],
            ['Hargreaves – endpoint', 'Hargreaves Test', 'D28 post-treatment', 200.0, 'planned', '+56 days', 20],
        ]);
        $this->createSurgeries($study5_2, $admin, $account, $subjects5_2, $at, [
            ['SNI surgery', InvasivenessType::Surgery, 'planned', '+22 days', 60],
            ['Terminal spinal cord sampling', InvasivenessType::Biopsy, 'planned', '+56 days', 30],
            ['DRG extraction', InvasivenessType::Biopsy, 'planned', '+56 days', 20],
        ]);
        $this->createWeightSeries($subjects5_2, $admin, $account, $at, '+17 days', 7, 1);

        // ── Connected Apps ────────────────────────────────────────────────────
        $connectedApps = [
            [
                'code' => AppCode::Elabftw,
                'name' => 'eLabFTW (Demo)',
                'externalUrl' => 'https://example.invalid/elabftw',
                'authenticationParameters' => ['token' => 'placeholder-token'],
            ],
            [
                'code' => AppCode::Fair3r,
                'name' => 'Fair3R (Demo)',
                'externalUrl' => 'https://validation.fair3r.fr',
                'authenticationParameters' => ['accessToken' => 'placeholder-token'],
            ],
            [
                'code' => AppCode::Osf,
                'name' => 'OSF (Demo)',
                'externalUrl' => 'https://osf.io',
                'authenticationParameters' => ['accessToken' => 'placeholder-token'],
            ],
            [
                'code' => AppCode::ProtocolIo,
                'name' => 'protocols.io (Demo)',
                'externalUrl' => 'https://www.protocols.io',
                'authenticationParameters' => null,
            ],
            [
                'code' => AppCode::PreclinicalTrials,
                'name' => 'PreclinicalTrials.eu (Demo)',
                'externalUrl' => 'https://preclinicaltrials.eu/api/external/viewable-protocols',
                'authenticationParameters' => null,
            ],
            [
                'code' => AppCode::Cedar,
                'name' => 'CEDAR (Demo)',
                'externalUrl' => 'https://resource.metadatacenter.org',
                'authenticationParameters' => null,
            ],
            [
                'code' => AppCode::BioPortal,
                'name' => 'BioPortal (Demo)',
                'externalUrl' => 'https://data.bioontology.org',
                'authenticationParameters' => null,
            ],
            [
                'code' => AppCode::Tecniplast,
                'name' => 'Tecniplast DVC (Demo)',
                'externalUrl' => 'https://example.invalid/tecniplast',
                'authenticationParameters' => ['apiKey' => 'placeholder-token'],
            ],
        ];

        foreach ($connectedApps as $connectedApp) {
            ConnectedAppFactory::createOne([
                'code' => $connectedApp['code'],
                'name' => $connectedApp['name'],
                'description' => 'Demo connection with mocked HTTP responses in dev/test environments.',
                'user' => $admin,
                'account' => $account,
                'isActive' => true,
                'externalUrl' => $connectedApp['externalUrl'],
                'token' => null,
                'authenticationParameters' => $connectedApp['authenticationParameters'],
            ]);
        }

        ConnectedAppFactory::createOne([
            'code' => AppCode::SensorAgent,
            'name' => 'Sovereign Sensor Agent',
            'description' => 'Read-only live sensor demo proxy for Raspberry Pi observations.',
            'user' => $admin,
            'account' => $account,
            'isActive' => true,
        ]);

        ConnectedResourceLinkFactory::createOne([
            'provider' => 'elabftw',
            'externalId' => 'ELAB-EXP-1001',
            'resourceType' => 'experiment',
            'label' => 'eLabFTW – Baseline Phenotyping protocol',
            'url' => 'https://example.invalid/elabftw/experiments/1001',
            'experiment' => $study1_1,
            'account' => $account,
        ]);

        ConnectedResourceLinkFactory::createOne([
            'provider' => 'elabftw',
            'externalId' => 'ELAB-EXP-1002',
            'resourceType' => 'experiment',
            'label' => 'eLabFTW – Light/Dark challenge',
            'url' => 'https://example.invalid/elabftw/experiments/1002',
            'experiment' => $study1_2,
            'account' => $account,
        ]);

        ConnectedResourceLinkFactory::createOne([
            'provider' => 'fair3r',
            'externalId' => 'F3R-DATASET-42',
            'resourceType' => 'repository',
            'label' => 'FAIR dataset – Circadian Rhythms Investigation',
            'url' => 'https://validation.fair3r.fr/dataset/F3R-DATASET-42',
            'experiment' => $study1_1,
            'account' => $account,
        ]);

        ConnectedResourceLinkFactory::createOne([
            'provider' => 'osf',
            'externalId' => 'OSF-CIRC-01',
            'resourceType' => 'repository',
            'label' => 'OSF – Circadian data repository',
            'url' => 'https://example.invalid/osf/CIRC01',
            'experiment' => $study1_3,
            'account' => $account,
        ]);

        ConnectedResourceLinkFactory::createOne([
            'provider' => 'elabftw',
            'externalId' => 'ELAB-EXP-2001',
            'resourceType' => 'experiment',
            'label' => 'eLabFTW – CCI model setup',
            'url' => 'https://example.invalid/elabftw/experiments/2001',
            'experiment' => $study5_1,
            'account' => $account,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function createHcmBenchmarkSystems(
        object $project,
        object $user,
        object $account,
        object $animalFacility,
        object $housing,
        object $strain,
        callable $at,
    ): void {
        $systems = [
            [
                'name' => 'Tecniplast DVC',
                'prefix' => 'DVC-BENCH',
                'source' => 'tecniplast_dvc',
                'modalities' => ['activity index', 'environment telemetry', 'sinusoid extraction'],
                'amplitudeBase' => 18.4,
                'mesorBase' => 42.0,
                'phaseBase' => 1.62,
                'peakHourBase' => 18.2,
            ],
            [
                'name' => 'Olden Labs HCM',
                'prefix' => 'OLDEN-HCM',
                'source' => 'olden_labs_hcm',
                'modalities' => ['video activity index', 'video immobility bouts', 'sinusoid extraction'],
                'amplitudeBase' => 16.8,
                'mesorBase' => 39.5,
                'phaseBase' => 1.48,
                'peakHourBase' => 17.7,
            ],
            [
                'name' => 'Live Mouse Tracker',
                'prefix' => 'LMT',
                'source' => 'live_mouse_tracker',
                'modalities' => ['RFID occupancy', 'video tracking distance', 'social proximity'],
                'amplitudeBase' => 22.7,
                'mesorBase' => 47.0,
                'phaseBase' => 1.92,
                'peakHourBase' => 19.4,
            ],
            [
                'name' => 'Open-BEATBox',
                'prefix' => 'BEATBOX',
                'source' => 'open_beatbox',
                'modalities' => ['video tracking distance', 'cognitive nose-poke events', 'in vivo calcium signal'],
                'amplitudeBase' => 14.9,
                'mesorBase' => 36.8,
                'phaseBase' => 1.28,
                'peakHourBase' => 16.9,
            ],
        ];

        $environmentModalities = ['temperature', 'humidity', 'light intensity', 'noise', 'human movement workday events'];

        foreach ($systems as $systemIndex => $system) {
            $variablesByModality = [];
            foreach ([...$system['modalities'], ...$environmentModalities] as $modality) {
                $variablesByModality[$modality] = VariableFactory::createOne([
                    'name' => $system['name'] . ' ' . $modality,
                    'account' => $account,
                ]);
            }

            $experiment = ExperimentFactory::createOne([
                'name' => $system['name'] . ' benchmark extraction',
                'goal' => 'Compare HCM system modality metadata and fitted circadian sinusoids across equivalent mouse cohorts.',
                'description' => 'Benchmark fixture for HomeCage Monitoring system comparison and scientific output generation.',
                'project' => $project,
                'user' => $user,
                'account' => $account,
                'type' => ExperimentType::TypeA,
                'startAt' => $at('-10 days'),
                'endAt' => $at('-3 days'),
                'protocol' => 'HCM benchmark: ' . implode(', ', $system['modalities']),
            ]);

            $hcm = HomeCageMonitoringFactory::createOne([
                'experiment' => $experiment,
                'system' => $system['name'],
                'account' => $account,
                'lightsOnUtcHour' => 6,
                'lightDurationHours' => 12,
            ]);

            foreach (range(0, 9) as $cageIndex) {
                $temperature = 20.8 + ($systemIndex * 0.35) + ($cageIndex * 0.12);
                $humidity = 47.0 + ($systemIndex * 2.0) + ($cageIndex % 5);
                $lightIntensity = 120.0 + ($systemIndex * 35.0) + ($cageIndex * 9.0);
                $noise = 38.0 + ($systemIndex * 3.0) + ($cageIndex % 4) * 1.5;
                $humanMovement = 6.0 + ($systemIndex * 1.4) + ($cageIndex % 5) * 0.8;
                $cageHousing = EnvironmentHousingFactory::createOne([
                    'animalFacility' => $animalFacility,
                    'account' => $account,
                    'room' => \sprintf('HCM-%s-%02d', $system['prefix'], $cageIndex + 1),
                    'temperature' => round($temperature, 1),
                    'humidity' => round($humidity, 1),
                    'luminosity' => round($lightIntensity, 1),
                    'noise' => round($noise, 1),
                    'lightCycle' => LightCycle::ON_08_OFF_20,
                    'experiment' => $experiment,
                ]);

                $cage = CageFactory::createOne([
                    'animalFacility' => $animalFacility,
                    'environmentHousing' => $cageHousing,
                    'homeCageMonitoring' => $hcm,
                    'account' => $account,
                    'externalId' => \sprintf('%s-CAGE-%02d', $system['prefix'], $cageIndex + 1),
                ]);

                SubjectFactory::createOne([
                    'name' => \sprintf('%s-MOUSE-%02d', $system['prefix'], $cageIndex + 1),
                    'species' => Species::Mouse,
                    'sex' => 0 === $cageIndex % 2 ? Sex::Male : Sex::Female,
                    'strain' => $strain,
                    'cage' => $cage,
                    'account' => $account,
                    'birthAt' => $at('-84 days +' . ($systemIndex + $cageIndex) . ' days'),
                    'genotype' => 0 === $cageIndex % 2 ? 'Shank3+/+' : 'Shank3+/-',
                    'externalId' => \sprintf('%s-RFID-%04d', $system['prefix'], $cageIndex + 1),
                ]);

                CageHcmSinusoidMetadataFactory::createOne([
                    'cage' => $cage,
                    'homeCageMonitoring' => $hcm,
                    'account' => $account,
                    'date' => $at('-6 days +' . $cageIndex . ' days'),
                    'amplitude' => round($system['amplitudeBase'] + ($cageIndex * 0.7), 2),
                    'mesor' => round($system['mesorBase'] + ($cageIndex * 0.85), 2),
                    'phase' => round($system['phaseBase'] + ($cageIndex * 0.045), 3),
                    'peakHour' => round($system['peakHourBase'] + ($cageIndex * 0.16), 2),
                    'sourceTaskId' => \sprintf('%s-TASK-%02d', strtoupper((string) $system['source']), $cageIndex + 1),
                    'sourceAppCode' => $system['source'],
                ]);

                foreach ([...$system['modalities'], ...$environmentModalities] as $modalityIndex => $modality) {
                    $value = match ($modality) {
                        'temperature' => $temperature,
                        'humidity' => $humidity,
                        'light intensity' => $lightIntensity,
                        'noise' => $noise,
                        'human movement workday events' => $humanMovement,
                        default => 18.0 + ($systemIndex * 4.5) + ($cageIndex * 2.1) + $modalityIndex,
                    };

                    CageHcmMeasureFactory::createOne([
                        'cage' => $cage,
                        'variable' => $variablesByModality[$modality],
                        'dataType' => str_contains($modality, 'sinusoid') ? HcmDataType::Sinusoid : HcmDataType::Activity,
                        'value' => round($value, 2),
                        'recordedAt' => $at('-6 days +' . $modalityIndex . ' hours'),
                        'homeCageMonitoring' => $hcm,
                        'account' => $account,
                        'sourceTaskId' => \sprintf('%s-MEASURE-%02d-%02d', strtoupper((string) $system['source']), $cageIndex + 1, $modalityIndex + 1),
                        'sourceAppCode' => $system['source'],
                    ]);
                }
            }
        }
    }

    private function createCages(
        int $count,
        object $animalFacility,
        object $housing,
        object $hcm,
        object $account,
        string $prefix,
    ): array {
        $cages = [];
        foreach (range(1, $count) as $i) {
            $cages[] = CageFactory::createOne([
                'animalFacility' => $animalFacility,
                'environmentHousing' => $housing,
                'homeCageMonitoring' => $hcm,
                'account' => $account,
                'externalId' => \sprintf('%s-CAGE-%02d', $prefix, $i),
            ]);
        }

        return $cages;
    }

    private function createSubjects(
        int $count,
        Sex $sex,
        Species $species,
        object $strain,
        array $cages,
        object $account,
        string $prefix,
        \DateTime $birthAt,
    ): array {
        $subjects = [];
        foreach (range(1, $count) as $i) {
            $subjects[] = SubjectFactory::createOne([
                'name' => \sprintf('%s-%04d', $prefix, $i),
                'sex' => $sex,
                'species' => $species,
                'strain' => $strain,
                'cage' => $cages[($i - 1) % \count($cages)],
                'account' => $account,
                'birthAt' => (clone $birthAt)->modify(\sprintf('+%d days', ($i - 1) * 2)),
                'elaftwExternalId' => \sprintf('ELAB-%s-%04d', $prefix, $i),
                'fair3rExternalId' => \sprintf('F3R-%s-%04d', $prefix, $i),
            ]);
        }

        return $subjects;
    }

    private function createTreatments(
        object $experiment,
        object $user,
        object $account,
        array $subjects,
        callable $at,
        array $definitions,
    ): void {
        $groupSize = (int) ceil(\count($subjects) / \count($definitions));
        foreach ($definitions as $idx => [$name, $type, $category, $route, $drug, $dose, $unit, $state, $startMod, $durationMin]) {
            $startAt = $at($startMod);
            $endAt = (clone $startAt)->modify(\sprintf('+%d minutes', $durationMin));
            $treatment = TreatmentFactory::createOne([
                'name' => $name,
                'experiment' => $experiment,
                'account' => $account,
                'user' => $user,
                'startAt' => $startAt,
                'endAt' => $endAt,
                'duration' => $durationMin * 60,
                'state' => $state,
                'treatmentType' => $type,
                'category' => $category,
                'route' => $route,
                'drug' => $drug,
                'dose' => $dose,
                'doseUnit' => $unit,
            ]);
            $groupSubjects = \array_slice($subjects, $idx * $groupSize, $groupSize);
            foreach ($groupSubjects as $subject) {
                $treatment->addSubject($subject);
            }
            save($treatment);
        }
    }

    private function createBehaviors(
        object $experiment,
        object $user,
        object $account,
        array $subjects,
        callable $at,
        array $definitions,
    ): void {
        foreach ($definitions as [$name, $apparatus, $context, $lighting, $state, $startMod, $durationMin]) {
            $startAt = $at($startMod);
            $endAt = (clone $startAt)->modify(\sprintf('+%d minutes', $durationMin));
            $behavior = BehaviorFactory::createOne([
                'name' => $name,
                'experiment' => $experiment,
                'account' => $account,
                'user' => $user,
                'startAt' => $startAt,
                'endAt' => $endAt,
                'duration' => $durationMin * 60,
                'state' => $state,
                'apparatus' => $apparatus,
                'context' => $context,
                'lighting' => $lighting,
            ]);
            foreach ($subjects as $subject) {
                $behavior->addSubject($subject);
            }
            save($behavior);
        }
    }

    private function createSurgeries(
        object $experiment,
        object $user,
        object $account,
        array $subjects,
        callable $at,
        array $definitions,
    ): void {
        foreach ($definitions as [$name, $invasiveness, $state, $startMod, $durationMin]) {
            $startAt = $at($startMod);
            $endAt = (clone $startAt)->modify(\sprintf('+%d minutes', $durationMin));
            $surgery = SurgeryFactory::createOne([
                'name' => $name,
                'experiment' => $experiment,
                'account' => $account,
                'user' => $user,
                'startAt' => $startAt,
                'endAt' => $endAt,
                'duration' => $durationMin * 60,
                'state' => $state,
                'invasivenessType' => $invasiveness,
            ]);
            foreach ($subjects as $subject) {
                $surgery->addSubject($subject);
            }
            save($surgery);
        }
    }

    private function createWeightSeries(
        array $subjects,
        object $user,
        object $account,
        callable $at,
        string $firstMeasurement,
        int $intervalDays,
        int $numMeasurements,
    ): void {
        foreach ($subjects as $idx => $subject) {
            $baseWeight = match ($subject->getSpecies()) {
                Species::Mouse => 0.022 + ($idx * 0.0003),
                Species::Rat => 0.220 + ($idx * 0.003),
                default => 0.022,
            };
            $first = $at($firstMeasurement);
            foreach (range(0, $numMeasurements - 1) as $step) {
                $measuredAt = clone $first;
                $measuredAt->modify(\sprintf('+%d days', $step * $intervalDays));
                WeightMeasurementFactory::createOne([
                    'subject' => $subject,
                    'user' => $user,
                    'account' => $account,
                    'weight' => $baseWeight + ($step * 0.0005),
                    'measuredAt' => $measuredAt,
                ]);
            }
        }
    }
}
