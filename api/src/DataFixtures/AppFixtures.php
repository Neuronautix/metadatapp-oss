<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Account;
use App\Entity\AnimalFacility;
use App\Entity\Behavior;
use App\Entity\Cage;
use App\Entity\CageHcmMeasure;
use App\Entity\ConnectedApp;
use App\Entity\ConnectedResourceLink;
use App\Entity\EnvironmentHousing;
use App\Entity\Experiment;
use App\Entity\HomeCageMonitoring;
use App\Entity\Laboratory;
use App\Entity\Project;
use App\Entity\Strain;
use App\Entity\Subject;
use App\Entity\Surgery;
use App\Entity\TimeSeries;
use App\Entity\Treatment;
use App\Entity\User;
use App\Entity\Variable;
use App\Entity\WeightMeasurement;
use App\Enum\AppCode;
use App\Enum\BeddingType;
use App\Enum\CageFormat;
use App\Enum\CageType;
use App\Enum\EnrichmentType;
use App\Enum\ExperimentType;
use App\Enum\HcmDataType;
use App\Enum\HealthStatus;
use App\Enum\InvasivenessType;
use App\Enum\Sex;
use App\Enum\Species;
use App\Enum\TreatmentCategory;
use App\Enum\TreatmentRoute;
use App\Enum\TreatmentType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ObjectManager;

/**
 * Todo ticket -> refacto app fixtures uisng Factory.
 */
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $account = (new Account())
            ->setName('Inst.@CorpOGN')
            ->setExternalKey('inst-corpogn')
        ;
        $manager->persist($account);
        $account2 = (new Account())
            ->setName('LaboR@T')
            ->setExternalKey('laborat')
        ;
        $manager->persist($account2);

        $superAdmin = (new User())
            ->setFirstName($firstname = 'Ineta')
            ->setLastName($lastname = 'Draft')
            ->setEmail($email = strtolower($firstname) . '.' . strtolower($lastname) . '@neuronautix.net')
            ->setAccount($account)
            ->setRoles(['ROLE_SUPER_ADMIN'])
        ;
        $manager->persist($superAdmin);

        $njukeUser = (new User())
            ->setFirstName($firstname = 'Njuke')
            ->setLastName($lastname = 'Oaye')
            ->setEmail('njuke.oaye@mdta.net')
            ->setAccount($account)
            ->setRoles(['ROLE_USER'])
        ;
        $manager->persist($njukeUser);
        $app = (new ConnectedApp())
            ->setDescription('SoftMouse is a web application that allows researchers to manage and analyze data from animal experiments.')
            ->setCode(AppCode::SoftMouse)
            ->setName('SoftMouse')
            ->setUser($njukeUser)
            ->setAccount($account)
            ->setIsActive(false)
        ;
        // $manager->persist($app);
        /** @var ConnectedApp $app */
        $app = (new ConnectedApp())
            ->setDescription('SoftMouse is a web application that allows researchers to manage and analyze data from animal experiments.')
            ->setCode(AppCode::SoftMouse)
            ->setName('SoftMouse')
            ->setUser($njukeUser)
            ->setAccount($account)
            ->setIsActive(false)
        ;
        $manager->persist($app);

        $yiolyo = (new User())
            ->setFirstName($firstname = 'doctor')
            ->setLastName($lastname = 'yiolyo')
            ->setEmail('doctor.yiolyo@mdta.net')
            ->setAccount($account)
            ->setRoles(['ROLE_ADMIN'])
        ;
        $manager->persist($yiolyo);
        /** @var ConnectedApp $app */
        $app = (new ConnectedApp())
            ->setDescription('SoftMouse is a web application that allows researchers to manage and analyze data from animal experiments.')
            ->setCode(AppCode::SoftMouse)
            ->setName('SoftMouse')
            ->setUser($yiolyo)
            ->setAccount($account)
            ->setIsActive(false)
        ;
        $manager->persist($app);
        $fair3r = (new ConnectedApp())
            ->setDescription('Fair3R')
            ->setCode(AppCode::Fair3r)
            ->setName('Fair3R')
            ->setUser($yiolyo)
            ->setAccount($account)
            ->setIsActive(false)
        ;
        $manager->persist($fair3r);
        $osf = (new ConnectedApp())
            ->setDescription('Open Science Framework repository integration for deposits and project metadata.')
            ->setCode(AppCode::Osf)
            ->setName('OSF')
            ->setUser($yiolyo)
            ->setAccount($account)
            ->setIsActive(false)
            ->setExternalUrl('https://osf.io')
        ;
        $manager->persist($osf);
        $protocolsIo = (new ConnectedApp())
            ->setDescription('protocols.io integration for protocol metadata and connection validation.')
            ->setCode(AppCode::ProtocolIo)
            ->setName('protocols.io')
            ->setUser($yiolyo)
            ->setAccount($account)
            ->setIsActive(false)
            ->setExternalUrl('https://www.protocols.io')
        ;
        $manager->persist($protocolsIo);
        $preclinicalTrials = (new ConnectedApp())
            ->setDescription('PreclinicalTrials.eu registry integration for importing protocol metadata.')
            ->setCode(AppCode::PreclinicalTrials)
            ->setName('PreclinicalTrials.eu')
            ->setUser($yiolyo)
            ->setAccount($account)
            ->setIsActive(false)
            ->setExternalUrl('https://preclinicaltrials.eu/api/external/viewable-protocols')
        ;
        $manager->persist($preclinicalTrials);
        $cedar = (new ConnectedApp())
            ->setDescription('CEDAR metadata template API integration.')
            ->setCode(AppCode::Cedar)
            ->setName('CEDAR')
            ->setUser($yiolyo)
            ->setAccount($account)
            ->setIsActive(false)
            ->setExternalUrl('https://resource.metadatacenter.org')
        ;
        $manager->persist($cedar);
        $bioPortal = (new ConnectedApp())
            ->setDescription('BioPortal ontology API integration.')
            ->setCode(AppCode::BioPortal)
            ->setName('BioPortal')
            ->setUser($yiolyo)
            ->setAccount($account)
            ->setIsActive(false)
            ->setExternalUrl('https://data.bioontology.org')
        ;
        $manager->persist($bioPortal);
        $jaxPhenome = (new ConnectedApp())
            ->setDescription('JAX Mouse Phenome Database (MPD) browse-only integration for exploring strains and phenotype measures.')
            ->setCode(AppCode::JaxPhenome)
            ->setName('JAX Phenome (MPD)')
            ->setUser($yiolyo)
            ->setAccount($account)
            ->setIsActive(false)
            ->setExternalUrl('https://phenome.jax.org')
        ;
        $manager->persist($jaxPhenome);
        $impc = (new ConnectedApp())
            ->setDescription('IMPReSS (IMPC) browse-only integration for searching standardized phenotyping procedures and parameters used in behavioral studies.')
            ->setCode(AppCode::Impc)
            ->setName('IMPReSS (IMPC)')
            ->setUser($yiolyo)
            ->setAccount($account)
            ->setIsActive(false)
            ->setExternalUrl('https://api.mousephenotype.org')
        ;
        $manager->persist($impc);
        $elabftw = (new ConnectedApp())
            ->setDescription('eLabFTW electronic lab notebook integration for protocol and experiment synchronization.')
            ->setCode(AppCode::Elabftw)
            ->setName('eLabFTW')
            ->setUser($yiolyo)
            ->setAccount($account)
            ->setIsActive(true)
            ->setExternalUrl('https://demo.elabftw.net')
            ->setToken('placeholder-token')
        ;
        $manager->persist($elabftw);
        $tecniplast = (new ConnectedApp())
            ->setDescription('Tecniplast DVC Analytics.')
            ->setCode(AppCode::Tecniplast)
            ->setName('Tecniplast')
            ->setUser($yiolyo)
            ->setAccount($account)
            ->setIsActive(false)
            ->setToken('eyJhbGciOiJIUzI1NiJ9.testkey')
        ;
        $manager->persist($tecniplast);
        $sensorAgent = (new ConnectedApp())
            ->setDescription('Read-only live sensor demo proxy for Raspberry Pi observations.')
            ->setCode(AppCode::SensorAgent)
            ->setName('Sovereign Sensor Agent')
            ->setUser($yiolyo)
            ->setAccount($account)
            ->setIsActive(true)
        ;
        $manager->persist($sensorAgent);
        $adminUser = (new User())
            ->setFirstName($firstname = 'Damien')
            ->setLastName($lastname = 'Huzard')
            ->setEmail($email = strtolower($firstname) . '.' . strtolower($lastname) . '@metadatapp.net')
            ->setAccount($account)
            ->setRoles(['ROLE_ADMIN'])
        ;
        $manager->persist($adminUser);

        $researcher = (new User())
            ->setFirstName($firstname = 'Naturel')
            ->setLastName($lastname = 'Drazuh')
            ->setEmail($email = strtolower($firstname) . '.' . strtolower($lastname) . '@mdma.io')
            ->setAccount($account2)
            ->setRoles(['ROLE_USER'])
        ;
        $manager->persist($researcher);

        //        $app = (new ConnectedApp())
        //            ->setDescription('Fair3R')
        //            ->setCode(AppCode::Fair3r)
        //            ->setName('Fair3R')
        //            ->setUser($researcher)
        //            ->setAccount($account)
        //            ->setIsActive(false)
        //        ;
        // $manager->persist($app);

        $animalFacility = (new AnimalFacility())
            ->setName('Animal Facility 1')
            ->setSanitaryStatus('EOPS')
            ->setAccount($account)
        ;
        $manager->persist($animalFacility);

        // Create Laboratory & Project
        $lab = (new Laboratory())
            ->setName('The DAX Lab (Deep Animal Xploration)')
            ->setAccount($account)
        ;
        $manager->persist($lab);

        $project = (new Project())
            ->setName('Itch hypersensitivity in ASD mouse models')
            ->setDescription('This project aims to investigate the role of the immune system in the development of itch hypersensitivity in mouse models of autism spectrum disorder.')
            ->setLaboratories(new ArrayCollection([$lab]))
            ->setRepositoryLink('https://osf.io/mn4xq/')
            ->setExternalId('tswm-yolio-xtx')
            ->setUser($njukeUser)
            ->setAccount($account)
        ;
        $manager->persist($project);

        $projectBw = (new Project())
            ->setName('The role of the insula in regulating social behavior')
            ->setDescription('This project aims to investigate the role of the insula in regulating social behavior in mouse models of autism spectrum disorder.')
            ->setLaboratories(new ArrayCollection([$lab]))
            ->setRepositoryLink('https://osf.io/6jcae/')
            ->setExternalId('tswm-yolio-xtx')
            ->setUser($yiolyo)
            ->setAccount($account)
        ;
        $manager->persist($projectBw);

        // Créer un EnvironmentHousing associé à l'Animal Facility
        $environmentHousing = (new EnvironmentHousing())
            ->setAnimalFacility($animalFacility)
            ->setRoom('Room 108')
            ->setHumidity(62.5)
            ->setLuminosity(300.0)
            ->setNoise(40.0)
            ->setPressure(1013.0)
            ->setTemperature(22.0)
            ->setAccount($account)
        ;
        $manager->persist($environmentHousing);

        // Create Cages
        $cage1 = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Standard)
            ->setFormat(CageFormat::T1)
            ->setBeddingType(BeddingType::Oak)
            ->setEnrichmentType(EnrichmentType::RunningWheel)
            ->setHasEnrichments(true)
            ->setWater(true)
            ->setFood(true)
            ->setAnimalFacility($animalFacility)
            ->setEnvironmentHousing($environmentHousing)
            ->setAccount($account)
        ;
        $manager->persist($cage1);

        $cage2 = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Standard)
            ->setFormat(CageFormat::T3)
            ->setBeddingType(BeddingType::Oak)
            ->setEnrichmentType(EnrichmentType::Wood)
            ->setHasEnrichments(true)
            ->setWater(true)
            ->setFood(true)
            ->setAnimalFacility($animalFacility)
            ->setEnvironmentHousing($environmentHousing)
            ->setAccount($account)
        ;
        $manager->persist($cage2);

        $cage3 = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Standard)
            ->setFormat(CageFormat::T1)
            ->setBeddingType(BeddingType::Oak)
            ->setEnrichmentType(EnrichmentType::Tunnel)
            ->setHasEnrichments(true)
            ->setWater(true)
            ->setFood(true)
            ->setAnimalFacility($animalFacility)
            ->setEnvironmentHousing($environmentHousing)
            ->setAccount($account)
        ;
        $manager->persist($cage3);

        $cage4 = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Standard)
            ->setFormat(CageFormat::T2)
            ->setBeddingType(BeddingType::Oak)
            ->setEnrichmentType(EnrichmentType::House)
            ->setHasEnrichments(true)
            ->setWater(true)
            ->setFood(true)
            ->setAnimalFacility($animalFacility)
            ->setEnvironmentHousing($environmentHousing)
            ->setAccount($account)
        ;
        $manager->persist($cage4);

        $cage5 = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Standard)
            ->setFormat(CageFormat::T1)
            ->setBeddingType(BeddingType::Oak)
            ->setHasEnrichments(false)
            ->setWater(true)
            ->setFood(true)
            ->setAnimalFacility($animalFacility)
            ->setEnvironmentHousing($environmentHousing)
            ->setAccount($account)
        ;
        $manager->persist($cage5);

        $cage6 = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Experimental)
            ->setFormat(CageFormat::T2)
            ->setBeddingType(BeddingType::Oak)
            ->setEnrichmentType(EnrichmentType::Toys)
            ->setHasEnrichments(true)
            ->setWater(true)
            ->setFood(true)
            ->setAnimalFacility($animalFacility)
            ->setEnvironmentHousing($environmentHousing)
            ->setAccount($account)
        ;
        $manager->persist($cage6);

        // 6. Create Subjects (Animals) & Strains
        $strainB6 = (new Strain())
            ->setName('C57BL/6J')
        ;
        $manager->persist($strainB6);

        $strainS3 = (new Strain())
            ->setName('Shank3')
        ;
        $manager->persist($strainS3);

        $subject1 = (new Subject())
            ->setName('I-1')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($cage1)
            ->setWeight(25.2)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2024-07-05'))
            ->setGenotype('KO')
            ->setAccount($account)
            ->setExternalId('189')
        ;
        $manager->persist($subject1);

        $subject2 = (new Subject())
            ->setName('1-II')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($cage1)
            ->setWeight(24.2)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2024-07-01'))
            ->setGenotype('KO')
            ->setAccount($account)
            ->setExternalId('64')
        ;
        $manager->persist($subject2);

        $subject3 = (new Subject())
            ->setName('1-III')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($cage1)
            ->setWeight(25.7)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2024-07-03'))
            ->setGenotype('KO')
            ->setAccount($account)
            ->setExternalId('65')
        ;
        $manager->persist($subject3);

        $subject4 = (new Subject())
            ->setName('2-I')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($cage2)
            ->setWeight(25.9)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2024-07-05'))
            ->setGenotype('WT')
            ->setAccount($account)
            ->setExternalId('68')
        ;
        $manager->persist($subject4);

        $subject5 = (new Subject())
            ->setName('2-II')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($cage2)
            ->setWeight(26.7)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2024-07-04'))
            ->setGenotype('WT')
            ->setAccount($account)
        ;
        $manager->persist($subject5);

        $subject6 = (new Subject())
            ->setName('2-III')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($cage2)
            ->setWeight(27.1)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2024-07-03'))
            ->setGenotype('WT')
            ->setAccount($account)
        ;
        $manager->persist($subject6);

        // 7. Create Experiment & Home Cage Monitoring
        $experimentBw = (new Experiment())
            ->setName('Link between Shank3 and Itch ?')
            ->setProject($projectBw)
            ->setUser($yiolyo)
            ->setStartAt(new \DateTime('2024-07-05'))
            ->setEndAt(new \DateTime('2024-07-10'))
            ->setDescription('This experiment aims to investigate the link between Shank3 and itch hypersensitivity in mouse models of autism spectrum disorder.')
            ->setRepositoryLink('https://osf.io/6jcae/')
            ->setType(ExperimentType::TypeA)
            ->setAccount($account)
        ;
        $manager->persist($experimentBw);

        $experiment2 = (new Experiment())
            ->setName('Link between itch and Skin deficits deficits deficits ?')
            ->setProject($project)
            ->setUser($researcher)
            ->setStartAt(new \DateTime('2024-08-05'))
            ->setEndAt(new \DateTime('2024-12-10'))
            ->setDescription('This experiment aims to investigate the link between itch and skin deficits in mouse models of autism spectrum disorder.')
            ->setRepositoryLink('https://osf.io/6jcae/')
            ->setType(ExperimentType::TypeA)
            ->setAccount($account)
        ;
        $manager->persist($experiment2);

        $hcm = (new HomeCageMonitoring())
            ->setExperiment($experimentBw)
            ->setSystem('HCM-System-X')
            ->setAccount($account)
        ;
        $manager->persist($hcm);

        // 7. Create Procedures (Behavior, Treatment, Surgery)
        // Behavior Procedure
        $behaviorProcedure = (new Behavior())
            ->setName('Open Field Test')
            ->setExperiment($experimentBw)
            ->setApparatus('Open Field Chamber 50x50x50 cm')
            ->setContext('Low lighting, quiet environment')
            ->setStartAt(new \DateTime('2024-07-05 10:00:00'))
            ->setEndAt(new \DateTime('2024-07-05 10:30:00'))
            ->setDuration(1800)
            ->setLighting(50.0)
            ->setState('planned')
            ->setAccount($account)
            ->setUser($yiolyo)
        ;
        $behaviorProcedure->addSubject($subject1);
        $behaviorProcedure->addSubject($subject2);
        $behaviorProcedure->addSubject($subject3);
        $manager->persist($behaviorProcedure);

        // Surgery Procedure
        $surgeryProcedure = (new Surgery())
            ->setName('Stereotaxic Surgery')
            ->setSurgeryName('Stereotaxic Surgery')
            ->setExperiment($experiment2)
            ->setSurgeryName('Cranial Implant') // Using the specific method for Surgery
            ->setInvasivenessType(InvasivenessType::Surgery)
            ->setInvasivenessDetails('Bilateral implantation of recording electrodes')
            ->setHasAnesthesia(true)
            ->setAnesthesiaDetails('Isoflurane 2% mixed with oxygen')
            ->setDuration(3600)
            ->setRecoveryDuration(3600 * 24 * 12)
            ->setState('planned')
            ->setSubjects(new ArrayCollection([$subject4, $subject5, $subject6]))
            ->setAccount($account)
            ->setUser($njukeUser)
        ;
        $manager->persist($surgeryProcedure);

        // Treatment Procedure
        $treatmentProcedure = (new Treatment())
            ->setName('Drug Injection')
            ->setExperiment($experiment2)
            ->setDrug('Diazepam') // Using the specific method for Treatment
            ->setDose(5.0)
            ->setDoseUnit('mg/kg')
            ->setRoute(TreatmentRoute::IP)
            ->setTreatmentType(TreatmentType::Pharmacological)
            ->setCategory(TreatmentCategory::ACUTE)
            ->setStartAt(new \DateTime('2024-07-06 09:00:00'))
            ->setState('planned')
            ->setSubjects(new ArrayCollection([$subject1, $subject2, $subject2, $subject4, $subject5, $subject6]))
            ->setAccount($account)
            ->setUser($njukeUser)
        ;
        $manager->persist($treatmentProcedure);

        // 8. Create Data Collection (Time Series, Weight, HCM Measures)
        $weight = (new WeightMeasurement())
            ->setSubject($subject1)
            ->setWeight(25.5)
            ->setMeasuredAt(new \DateTime('2024-07-05 09:00:00'))
            ->setUser($yiolyo)
            ->setAccount($account)
        ;
        $manager->persist($weight);

        $weight = (new WeightMeasurement())
            ->setSubject($subject1)
            ->setWeight(26.5)
            ->setMeasuredAt(new \DateTime('2024-08-06 09:00:00'))
            ->setUser($njukeUser)
            ->setAccount($account)
        ;
        $manager->persist($weight);

        $weight = (new WeightMeasurement())
            ->setSubject($subject1)
            ->setWeight(28.5)
            ->setMeasuredAt(new \DateTime('2024-08-07 09:00:00'))
            ->setUser($njukeUser)
            ->setAccount($account)
        ;
        $manager->persist($weight);

        $variable = (new Variable())
            ->setName('Activity Level')
            ->setAccount($account)
        ;
        $manager->persist($variable);

        $timeSeries = (new TimeSeries())
            ->setExperiment($experiment2)
            ->setSubject($subject1)
            ->setVariable($variable)
            ->setRecordedAt(new \DateTime('2024-07-05 09:00:00'))
            ->setValue('34.34500')
            ->setAccount($account)
        ;
        $manager->persist($timeSeries);

        $variable2 = (new Variable())
            ->setName('Food Intake') // Assure-toi que le nom est pertinent
            ->setAccount($account)
        ;
        $manager->persist($variable2);

        $homeCageMonitoring = (new HomeCageMonitoring())
            ->setExperiment($experiment2)
            ->setSystem('HCM-System-X') // Remplace par le bon système si nécessaire
            ->setAccount($account)
        ;
        $manager->persist($homeCageMonitoring);

        $hcmMeasure = (new CageHcmMeasure())
            ->setCage($cage1)
            ->setValue(8.7)
            ->setVariable($variable2)
            ->setHomeCageMonitoring($homeCageMonitoring) // ✅ Ajoute cette ligne
            ->setRecordedAt(new \DateTime('2024-07-05 09:00:00'))
            ->setDataType(HcmDataType::FoodIntake)
            ->setAccount($account)
        ;
        $manager->persist($hcmMeasure);

        // Golden demo slice: one linked FAIR experiment spanning ELN, animal management, and DVC activity data.
        $demoAnchor = new \DateTimeImmutable('today 06:00:00');
        $demoAt = static fn (string $modifier): \DateTime => \DateTime::createFromImmutable($demoAnchor->modify($modifier));
        $demoProject = (new Project())
            ->setName('Demo - DVC circadian rhythm monitoring in Shank3 mice')
            ->setGoal('Integrate eLabFTW protocol metadata, animal/cage records, and DVC home-cage activity into one reusable experiment graph.')
            ->setDescription('End-to-end demo project for FAIR-by-design metadata exchange across ELN, animal management, and Tecniplast DVC-style analytics.')
            ->setStartAt($demoAt('-7 days'))
            ->setEndAt($demoAt('+7 days'))
            ->setLaboratories(new ArrayCollection([$lab]))
            ->setRepositoryLink('https://osf.io/metadatapp-demo-dvc/')
            ->setExternalId('MAPP-DEMO-DVC-001')
            ->setElaftwExternalId('ELABFTW-PROJ-9001')
            ->setFair3rExternalId('FAIR3R-PROJ-DVC-001')
            ->setSoftmouseExternalId('SM-STUDY-DVC-001')
            ->setUser($yiolyo)
            ->setAccount($account)
        ;
        $lab->addProject($demoProject);
        $manager->persist($demoProject);

        $demoExperiment = (new Experiment())
            ->setName('DVC circadian activity acquisition')
            ->setGoal('Quantify daily activity rhythm differences between Shank3 knockout and wild-type mice under a 12:12 light/dark cycle.')
            ->setProject($demoProject)
            ->setUser($yiolyo)
            ->setStartAt($demoAt('-2 days'))
            ->setEndAt($demoAt('+5 days'))
            ->setDescription('Golden demo experiment linking a protocol, animals, cages, housing conditions, DVC activity measurements, and reusable export metadata.')
            ->setProtocol('ARRIVE-aligned home-cage monitoring protocol: acclimation, 12:12 light/dark cycle, continuous DVC activity acquisition, daily welfare check, and blinded genotype comparison.')
            ->setRepositoryLink('https://osf.io/metadatapp-demo-dvc/files/osfstorage')
            ->setType(ExperimentType::TypeA)
            ->setExternalId('MAPP-EXP-DVC-001')
            ->setElaftwExternalId('ELABFTW-EXP-4242')
            ->setElabftwMetadata([
                'protocol_url' => 'https://demo.elabftw.net/experiments/4242',
                'protocol_title' => 'DVC home-cage circadian rhythm monitoring',
                'experiment_date' => $demoAt('-2 days')->format('Y-m-d'),
                'operator' => 'doctor.yiolyo@mdta.net',
                'control_group' => 'Wild-type littermate cage as baseline comparator for knockout cage.',
                'experimental_unit' => 'individual animal tracked by RFID and grouped by cage for DVC summary.',
                'sample_size_rationale' => 'Power estimate targeting medium effect size in day/night activity ratio with alpha 0.05 and 80% power.',
                'inclusion_criteria' => 'Male mice, 11-13 weeks, healthy status, complete RFID tracking throughout acquisition window.',
                'exclusion_criteria' => 'Animals with persistent sensor dropout >6h, severe welfare concern, or protocol deviation before day 2.',
                'n_per_group' => 'KO: 1, WT: 1 in this demo fixture; production protocol targets >=8 per group.',
                'randomization' => 'Animals assigned to cages by genotype-balanced randomization list DVC-RAND-001.',
                'confounder_control' => 'Rack positions rotated at midpoint and measurements timestamp-normalized to UTC.',
                'blinding' => 'Activity analysis grouped by blinded animal code until QC sign-off.',
                'outcome_measures' => 'Hourly DVC activity counts, day/night activity ratio, and weekly body-weight drift.',
                'primary_outcome' => 'Difference in day/night activity ratio between Shank3-/- and Shank3+/+ groups.',
                'statistical_methods' => 'Mixed-effects model with cage as random intercept and genotype/time-window as fixed effects.',
                'statistical_assumption_checks' => 'Residual diagnostics, QQ plot inspection, and robust fallback for non-normal residuals.',
                'results_summary' => 'Knockout group shows elevated nocturnal hyperactivity signal compared with wild-type in the same light/dark cycle.',
                'results_variability' => 'Group means with SD and hourly trajectories plotted per animal and per cage.',
                'effect_size' => 'Standardized mean difference reported with 95% confidence interval in final analysis.',
                'abstract' => 'This ARRIVE demo links protocol metadata, animals, housing, and DVC activity exports for transparent reporting.',
                'background' => 'Shank3 models are widely used in neurodevelopmental studies; circadian and activity phenotypes remain heterogeneous across labs.',
                'objectives' => 'Assess whether knockout animals differ from littermate controls in circadian activity structure under standardized housing.',
                'ethical_statement' => 'Approved by local cantonal animal welfare committee under protocol BE-2026-0112.',
                'pain_distress_mitigation' => 'Daily welfare scoring and minimally invasive handling with predefined intervention thresholds.',
                'adverse_events' => 'No unexpected adverse events observed in this fixture window.',
                'humane_endpoints' => 'Endpoint trigger: sustained >15% weight loss, severe distress score, or persistent immobility.',
                'interpretation' => 'Observed phenotype is consistent with altered activity rhythms, but demo sample size limits inferential strength.',
                'generalisability' => 'Findings are illustrative for C57BL/6J male mice and should be validated across strains and sexes.',
                'protocol_registration' => 'Registered in eLabFTW workspace as ELABFTW-EXP-4242 before acquisition start.',
                'data_access' => 'Raw and processed activity traces available at OSF repository link in experiment metadata.',
                'conflicts_of_interest' => 'No competing financial or non-financial interests declared for the demo dataset.',
                'funding_sources' => 'Internal Neuronautix platform development funds; funder had no role in analysis decisions.',
                'light_dark_cycle' => 'Lights on 06:00, lights off 18:00, Zeitgeber time ZT0=06:00.',
            ])
            ->setFair3rExternalId('FAIR3R-EXP-DVC-001')
            ->setSoftmouseExternalId('SM-EXPERIMENT-DVC-001')
            ->setAccount($account)
        ;
        $manager->persist($demoExperiment);

        $demoSyncedAt = \DateTimeImmutable::createFromInterface($demoAt('-2 days'));
        foreach ([
            ['provider' => 'elabftw', 'externalId' => 'ELABFTW-EXP-4242', 'resourceType' => 'protocol', 'label' => 'DVC home-cage circadian rhythm monitoring', 'url' => 'https://demo.elabftw.net/experiments/4242', 'subject' => null],
            ['provider' => 'elabftw', 'externalId' => 'ELABFTW-PROC-4243', 'resourceType' => 'experiment', 'label' => 'Light/dark acclimation procedure log', 'url' => 'https://demo.elabftw.net/experiments/4243', 'subject' => null],
            ['provider' => 'tecniplast_dvc', 'externalId' => 'DVC-TASK-2026-03-09', 'resourceType' => 'extraction_task', 'label' => 'DVC activity extraction window 2026-03-03 → 2026-03-10', 'url' => null, 'subject' => null],
            ['provider' => 'softmouse', 'externalId' => 'SM-ANIMAL-7001', 'resourceType' => 'animal_record', 'label' => 'Shank3-/- male, cage DVC-CAGE-KO-01', 'url' => null, 'subject' => 'SM-ANIMAL-7001'],
            ['provider' => 'softmouse', 'externalId' => 'SM-ANIMAL-7002', 'resourceType' => 'animal_record', 'label' => 'Shank3+/+ male, cage DVC-CAGE-WT-01', 'url' => null, 'subject' => 'SM-ANIMAL-7002'],
            ['provider' => 'osf', 'externalId' => 'osf:metadatapp-demo-dvc', 'resourceType' => 'repository', 'label' => 'OSF dataset deposit', 'url' => 'https://osf.io/metadatapp-demo-dvc/', 'subject' => null],
        ] as $linkSeed) {
            $link = (new ConnectedResourceLink())
                ->setProvider($linkSeed['provider'])
                ->setExternalId($linkSeed['externalId'])
                ->setResourceType($linkSeed['resourceType'])
                ->setLabel($linkSeed['label'])
                ->setUrl($linkSeed['url'])
                ->setSubjectExternalId($linkSeed['subject'])
                ->setSyncedAt($demoSyncedAt)
                ->setExperiment($demoExperiment)
                ->setAccount($account)
            ;
            $manager->persist($link);
        }

        $demoHcm = (new HomeCageMonitoring())
            ->setExperiment($demoExperiment)
            ->setSystem('Tecniplast DVC')
            ->setAccount($account)
        ;
        $manager->persist($demoHcm);

        $demoCageKo = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Experimental)
            ->setFormat(CageFormat::T2)
            ->setBeddingType(BeddingType::Oak)
            ->setEnrichmentType(EnrichmentType::Tunnel)
            ->setHasEnrichments(true)
            ->setWater(true)
            ->setFood(true)
            ->setEnvironmentHousing($environmentHousing)
            ->setHomeCageMonitoring($demoHcm)
            ->setExternalId('DVC-CAGE-KO-01')
            ->setSoftmouseExternalId('SM-CAGE-4101')
            ->setAccount($account)
        ;
        $manager->persist($demoCageKo);

        $demoCageWt = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Experimental)
            ->setFormat(CageFormat::T2)
            ->setBeddingType(BeddingType::Oak)
            ->setEnrichmentType(EnrichmentType::Tunnel)
            ->setHasEnrichments(true)
            ->setWater(true)
            ->setFood(true)
            ->setEnvironmentHousing($environmentHousing)
            ->setHomeCageMonitoring($demoHcm)
            ->setExternalId('DVC-CAGE-WT-01')
            ->setSoftmouseExternalId('SM-CAGE-4102')
            ->setAccount($account)
        ;
        $manager->persist($demoCageWt);

        $demoSubjectKo = (new Subject())
            ->setName('DVC-KO-001')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($demoCageKo)
            ->setWeight(24.8)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2025-12-10'))
            ->setGenotype('Shank3-/-')
            ->setIsPregnant(false)
            ->setExternalId('RFID-840-0001')
            ->setSoftmouseExternalId('SM-ANIMAL-840-0001')
            ->setAccount($account)
        ;
        $manager->persist($demoSubjectKo);

        $demoSubjectWt = (new Subject())
            ->setName('DVC-WT-001')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainB6)
            ->setCage($demoCageWt)
            ->setWeight(25.6)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2025-12-12'))
            ->setGenotype('Shank3+/+')
            ->setIsPregnant(false)
            ->setExternalId('RFID-840-0002')
            ->setSoftmouseExternalId('SM-ANIMAL-840-0002')
            ->setAccount($account)
        ;
        $manager->persist($demoSubjectWt);

        $demoProcedure = (new Behavior())
            ->setName('Continuous DVC activity monitoring')
            ->setExperiment($demoExperiment)
            ->setApparatus('Tecniplast DVC rack, two T2 cages')
            ->setContext('Room 108, 12:12 light/dark cycle, ZT0 at 06:00')
            ->setStartAt($demoAt('-2 days'))
            ->setEndAt($demoAt('+5 days'))
            ->setDuration(604800)
            ->setLighting(300.0)
            ->setProtocol('Continuous non-invasive activity acquisition with daily welfare observations and cage-level DVC aggregation.')
            ->setProtocolUri('https://demo.elabftw.net/experiments/4242')
            ->setElaftwExternalId('ELABFTW-PROC-DVC-4242')
            ->setTecniplastExternalId('DVC-RUN-20260303-001')
            ->setState('completed')
            ->setAccount($account)
            ->setUser($yiolyo)
        ;
        $demoProcedure->addSubject($demoSubjectKo);
        $demoProcedure->addSubject($demoSubjectWt);
        $manager->persist($demoProcedure);

        $demoWelfareProcedure = (new Treatment())
            ->setName('Daily welfare and body weight check')
            ->setExperiment($demoExperiment)
            ->setDrug('Vehicle handling')
            ->setDose(0.0)
            ->setDoseUnit('mL/kg')
            ->setRoute(TreatmentRoute::IP)
            ->setTreatmentType(TreatmentType::Pharmacological)
            ->setCategory(TreatmentCategory::ACUTE)
            ->setStartAt($demoAt('+1 day'))
            ->setEndAt($demoAt('+1 day 45 minutes'))
            ->setState('planned')
            ->setSubjects(new ArrayCollection([$demoSubjectKo, $demoSubjectWt]))
            ->setAccount($account)
            ->setUser($yiolyo)
        ;
        $manager->persist($demoWelfareProcedure);

        foreach ([
            [$demoSubjectKo, 24.8, '-2 days 2 hours'],
            [$demoSubjectKo, 24.7, '+1 day 2 hours'],
            [$demoSubjectWt, 25.6, '-2 days 2 hours'],
            [$demoSubjectWt, 25.9, '+1 day 2 hours'],
        ] as [$subject, $weightValue, $measuredAtModifier]) {
            $manager->persist((new WeightMeasurement())
                ->setSubject($subject)
                ->setWeight($weightValue)
                ->setMeasuredAt($demoAt($measuredAtModifier))
                ->setUser($yiolyo)
                ->setAccount($account)
            );
        }

        $demoActivityVariable = (new Variable())
            ->setName('DVC activity count')
            ->setExternalId('OBI:0001444')
            ->setTecniplastExternalId('DVC_METRIC_ACTIVITY')
            ->setAccount($account)
        ;
        $manager->persist($demoActivityVariable);

        $demoFoodVariable = (new Variable())
            ->setName('DVC food intake')
            ->setExternalId('EFO:0004352')
            ->setTecniplastExternalId('DVC_METRIC_FOOD')
            ->setAccount($account)
        ;
        $manager->persist($demoFoodVariable);

        $demoActivityRows = [
            ['2026-03-03 06:00:00', 18.0, 21.0],
            ['2026-03-03 12:00:00', 32.0, 38.0],
            ['2026-03-03 18:00:00', 86.0, 72.0],
            ['2026-03-04 00:00:00', 112.0, 91.0],
            ['2026-03-04 06:00:00', 20.0, 23.0],
            ['2026-03-04 12:00:00', 35.0, 41.0],
            ['2026-03-04 18:00:00', 94.0, 76.0],
            ['2026-03-05 00:00:00', 118.0, 97.0],
        ];
        foreach ($demoActivityRows as [$recordedAt, $koValue, $wtValue]) {
            foreach ([[$demoSubjectKo, $koValue], [$demoSubjectWt, $wtValue]] as [$subject, $activityValue]) {
                $manager->persist((new TimeSeries())
                    ->setExperiment($demoExperiment)
                    ->setSubject($subject)
                    ->setVariable($demoActivityVariable)
                    ->setRecordedAt(new \DateTime($recordedAt))
                    ->setValue(number_format($activityValue, 5, '.', ''))
                    ->setUnit('count/6h')
                    ->setAccount($account)
                );
            }
        }

        foreach ([
            [$demoCageKo, 4.6, '2026-03-04 06:00:00'],
            [$demoCageKo, 5.1, '2026-03-05 06:00:00'],
            [$demoCageWt, 4.9, '2026-03-04 06:00:00'],
            [$demoCageWt, 5.0, '2026-03-05 06:00:00'],
        ] as [$cage, $foodValue, $recordedAt]) {
            $manager->persist((new CageHcmMeasure())
                ->setCage($cage)
                ->setValue($foodValue)
                ->setVariable($demoFoodVariable)
                ->setHomeCageMonitoring($demoHcm)
                ->setRecordedAt(new \DateTime($recordedAt))
                ->setDataType(HcmDataType::FoodIntake)
                ->setAccount($account)
            );
        }

        // ----- an other account -----

        $user1 = (new User())
            ->setFirstName($firstname = 'Ook')
            ->setLastName($lastname = 'iik')
            ->setEmail('ook.ook@labor.et')
            ->setAccount($account2)
            ->setRoles(['USER'])
        ;
        $manager->persist($njukeUser);
        $user2 = (new User())
            ->setFirstName($firstname = 'Docia')
            ->setLastName($lastname = 'Mocia')
            ->setEmail('docia.mocia@labor.et')
            ->setAccount($account2)
            ->setRoles(['USER'])
        ;
        $manager->persist($njukeUser);

        $yiolyoJr = (new User())
            ->setFirstName($firstname = 'doctor')
            ->setLastName($lastname = 'yiolyo jr')
            ->setEmail('doctor.yiolyo.jf@labor.et')
            ->setAccount($account2)
            ->setRoles(['ADMIN'])
        ;
        $manager->persist($yiolyoJr);
        /** @var ConnectedApp $app */
        $app = (new ConnectedApp())
            ->setDescription('SoftMouse is a web application that allows researchers to manage and analyze data from animal experiments.')
            ->setCode(AppCode::SoftMouse)
            ->setName('SoftMouse')
            ->setUser($yiolyoJr)
            ->setAccount($account2)
            ->setIsActive(false)
        ;
        // $manager->persist($app);

        $tecniplast2 = (new ConnectedApp())
            ->setDescription('Tecniplast DVC Analytics.')
            ->setCode(AppCode::Tecniplast)
            ->setName('Tecniplast')
            ->setUser($yiolyoJr)
            ->setAccount($account2)
            ->setIsActive(false)
            ->setToken('eyJhbGciOiJIUzI1NiJ9.testkey')
        ;
        $manager->persist($tecniplast2);
        $sensorAgent2 = (new ConnectedApp())
            ->setDescription('Read-only live sensor demo proxy for Raspberry Pi observations.')
            ->setCode(AppCode::SensorAgent)
            ->setName('Sovereign Sensor Agent')
            ->setUser($yiolyoJr)
            ->setAccount($account2)
            ->setIsActive(true)
        ;
        $manager->persist($sensorAgent2);

        $animalFacility = (new AnimalFacility())
            ->setName('Animal Facility 1')
            ->setSanitaryStatus('EOPS')
            ->setAccount($account2)
        ;
        $manager->persist($animalFacility);

        // Create Laboratory & Project
        $lab = (new Laboratory())
            ->setName('The DAX Lab (Deep Animal Xploration)')
            ->setAccount($account2)
        ;
        $manager->persist($lab);

        $project = (new Project())
            ->setName('Itch hypersensitivity in ASD mouse models')
            ->setDescription('This project aims to investigate the role of the immune system in the development of itch hypersensitivity in mouse models of autism spectrum disorder.')
            ->setLaboratories(new ArrayCollection([$lab]))
            ->setRepositoryLink('https://osf.io/mn4xq/')
            ->setUser($njukeUser)
            ->setAccount($account2)
        ;
        $manager->persist($project);

        $project = (new Project())
            ->setName('The role of the insula in regulating social behavior')
            ->setDescription('This project aims to investigate the role of the insula in regulating social behavior in mouse models of autism spectrum disorder.')
            ->setLaboratories(new ArrayCollection([$lab]))
            ->setRepositoryLink('https://osf.io/6jcae/')
            ->setExternalId('f3r_bw_table')
            ->setUser($njukeUser)
            ->setAccount($account2)
        ;
        $manager->persist($project);

        // Créer un EnvironmentHousing associé à l'Animal Facility
        $environmentHousing = (new EnvironmentHousing())
            ->setAnimalFacility($animalFacility)
            ->setRoom('Room 108')
            ->setHumidity(62.5)
            ->setLuminosity(300.0)
            ->setNoise(40.0)
            ->setPressure(1013.0)
            ->setTemperature(22.0)
            ->setAccount($account2)
        ;
        $manager->persist($environmentHousing);

        // Create Cages
        $cage1 = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Standard)
            ->setFormat(CageFormat::T1)
            ->setBeddingType(BeddingType::Oak)
            ->setEnrichmentType(EnrichmentType::RunningWheel)
            ->setHasEnrichments(true)
            ->setWater(true)
            ->setFood(true)
            ->setAnimalFacility($animalFacility)
            ->setEnvironmentHousing($environmentHousing)
            ->setAccount($account2)
        ;
        $manager->persist($cage1);

        $cage2 = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Standard)
            ->setFormat(CageFormat::T3)
            ->setBeddingType(BeddingType::Oak)
            ->setEnrichmentType(EnrichmentType::Wood)
            ->setHasEnrichments(true)
            ->setWater(true)
            ->setFood(true)
            ->setAnimalFacility($animalFacility)
            ->setEnvironmentHousing($environmentHousing)
            ->setAccount($account2)
        ;
        $manager->persist($cage2);

        $cage3 = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Standard)
            ->setFormat(CageFormat::T1)
            ->setBeddingType(BeddingType::Oak)
            ->setEnrichmentType(EnrichmentType::Tunnel)
            ->setHasEnrichments(true)
            ->setWater(true)
            ->setFood(true)
            ->setAnimalFacility($animalFacility)
            ->setEnvironmentHousing($environmentHousing)
            ->setAccount($account2)
        ;
        $manager->persist($cage3);

        $cage4 = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Standard)
            ->setFormat(CageFormat::T2)
            ->setBeddingType(BeddingType::Oak)
            ->setEnrichmentType(EnrichmentType::House)
            ->setHasEnrichments(true)
            ->setWater(true)
            ->setFood(true)
            ->setAnimalFacility($animalFacility)
            ->setEnvironmentHousing($environmentHousing)
            ->setAccount($account2)
        ;
        $manager->persist($cage4);

        $cage5 = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Standard)
            ->setFormat(CageFormat::T1)
            ->setBeddingType(BeddingType::Oak)
            ->setHasEnrichments(false)
            ->setWater(true)
            ->setFood(true)
            ->setAnimalFacility($animalFacility)
            ->setEnvironmentHousing($environmentHousing)
            ->setAccount($account2)
        ;
        $manager->persist($cage5);

        $cage6 = (new Cage())
            ->setAnimalFacility($animalFacility)
            ->setType(CageType::Experimental)
            ->setFormat(CageFormat::T2)
            ->setBeddingType(BeddingType::Oak)
            ->setEnrichmentType(EnrichmentType::Toys)
            ->setHasEnrichments(true)
            ->setWater(true)
            ->setFood(true)
            ->setAnimalFacility($animalFacility)
            ->setEnvironmentHousing($environmentHousing)
            ->setAccount($account2)
        ;
        $manager->persist($cage6);

        // 6. Create Subjects (Animals) & Strains
        $strainB6 = (new Strain())
            ->setName('C57BL/6J')
        ;
        $manager->persist($strainB6);

        $strainS3 = (new Strain())
            ->setName('Shank3')
        ;
        $manager->persist($strainS3);

        $subject1 = (new Subject())
            ->setName('I-1')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($cage1)
            ->setWeight(25.2)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2024-07-05'))
            ->setGenotype('KO')
            ->setAccount($account2)
        ;
        $manager->persist($subject1);

        $subject2 = (new Subject())
            ->setName('1-II')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($cage1)
            ->setWeight(24.2)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2024-07-01'))
            ->setGenotype('KO')
            ->setAccount($account2)
        ;
        $manager->persist($subject2);

        $subject3 = (new Subject())
            ->setName('1-III')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($cage1)
            ->setWeight(25.7)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2024-07-03'))
            ->setGenotype('KO')
            ->setAccount($account2)
        ;
        $manager->persist($subject3);

        $subject4 = (new Subject())
            ->setName('2-I')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($cage2)
            ->setWeight(25.9)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2024-07-05'))
            ->setGenotype('WT')
            ->setAccount($account2)
        ;
        $manager->persist($subject4);

        $subject5 = (new Subject())
            ->setName('2-II')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($cage2)
            ->setWeight(26.7)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2024-07-04'))
            ->setGenotype('WT')
            ->setAccount($account2)
        ;
        $manager->persist($subject5);

        $subject6 = (new Subject())
            ->setName('2-III')
            ->setSpecies(Species::Mouse)
            ->setStrain($strainS3)
            ->setCage($cage2)
            ->setWeight(27.1)
            ->setHealthStatus(HealthStatus::NORMAL)
            ->setSex(Sex::Male)
            ->setBirthAt(new \DateTime('2024-07-03'))
            ->setGenotype('WT')
            ->setAccount($account2)
        ;
        $manager->persist($subject6);

        // 7. Create Experiment & Home Cage Monitoring
        $experiment = (new Experiment())
            ->setName('Link between Shank3 and Itch ?')
            ->setProject($project)
            ->setUser($researcher)
            ->setStartAt(new \DateTime('2024-07-05'))
            ->setEndAt(new \DateTime('2024-07-10'))
            ->setDescription('This experiment aims to investigate the link between Shank3 and itch hypersensitivity in mouse models of autism spectrum disorder.')
            ->setRepositoryLink('https://osf.io/6jcae/')
            ->setType(ExperimentType::TypeA)
            ->setAccount($account2)
        ;
        $manager->persist($experiment);

        $experiment2 = (new Experiment())
            ->setName('Link between itch and Skin deficits ?')
            ->setProject($project)
            ->setUser($researcher)
            ->setStartAt(new \DateTime('2024-08-05'))
            ->setEndAt(new \DateTime('2024-12-10'))
            ->setDescription('This experiment aims to investigate the link between itch and skin deficits in mouse models of autism spectrum disorder.')
            ->setRepositoryLink('https://osf.io/6jcae/')
            ->setType(ExperimentType::TypeA)
            ->setAccount($account2)
        ;
        $manager->persist($experiment2);

        $hcm = (new HomeCageMonitoring())
            ->setExperiment($experiment)
            ->setSystem('HCM-System-X')
            ->setAccount($account2)
        ;
        $manager->persist($hcm);

        // 7. Create Procedures (Behavior, Treatment, Surgery)
        // Behavior Procedure
        $behaviorProcedure = (new Behavior())
            ->setName('Open Field Test')
            ->setExperiment($experiment)
            ->setApparatus('Open Field Chamber 50x50x50 cm')
            ->setContext('Low lighting, quiet environment')
            ->setStartAt(new \DateTime('2024-07-05 10:00:00'))
            ->setEndAt(new \DateTime('2024-07-05 10:30:00'))
            ->setDuration(1800)
            ->setLighting(50.0)
            ->setState('planned')
            ->setSubjects(new ArrayCollection([$subject1, $subject2, $subject3]))
            ->setAccount($account2)
            ->setUser($researcher)
        ;
        $manager->persist($behaviorProcedure);

        // Surgery Procedure
        $surgeryProcedure = (new Surgery())
            ->setName('Stereotaxic Surgery')
            ->setSurgeryName('Stereotaxic Surgery')
            ->setExperiment($experiment)
            ->setSurgeryName('Cranial Implant') // Using the specific method for Surgery
            ->setInvasivenessType(InvasivenessType::Surgery)
            ->setInvasivenessDetails('Bilateral implantation of recording electrodes')
            ->setHasAnesthesia(true)
            ->setAnesthesiaDetails('Isoflurane 2% mixed with oxygen')
            ->setDuration(3600)
            ->setRecoveryDuration(3600 * 24 * 12)
            ->setState('planned')
            ->setSubjects(new ArrayCollection([$subject4, $subject5, $subject6]))
            ->setAccount($account2)
            ->setUser($researcher)
        ;
        $manager->persist($surgeryProcedure);

        // Treatment Procedure
        $treatmentProcedure = (new Treatment())
            ->setName('Drug Injection')
            ->setExperiment($experiment)
            ->setDrug('Diazepam') // Using the specific method for Treatment
            ->setDose(5.0)
            ->setDoseUnit('mg/kg')
            ->setRoute(TreatmentRoute::IP)
            ->setTreatmentType(TreatmentType::Pharmacological)
            ->setCategory(TreatmentCategory::ACUTE)
            ->setStartAt(new \DateTime('2024-07-06 09:00:00'))
            ->setState('planned')
            ->setSubjects(new ArrayCollection([$subject1, $subject2, $subject2, $subject4, $subject5, $subject6]))
            ->setAccount($account2)
            ->setUser($researcher)
        ;
        $manager->persist($treatmentProcedure);

        // 8. Create Data Collection (Time Series, Weight, HCM Measures)
        $weight = (new WeightMeasurement())
            ->setSubject($subject1)
            ->setWeight(25.5)
            ->setMeasuredAt(new \DateTime('2024-07-05 09:00:00'))
            ->setUser($researcher)
            ->setAccount($account2)
        ;
        $manager->persist($weight);

        $weight = (new WeightMeasurement())
            ->setSubject($subject1)
            ->setWeight(26.5)
            ->setMeasuredAt(new \DateTime('2024-08-06 09:00:00'))
            ->setUser($researcher)
            ->setAccount($account2)
        ;
        $manager->persist($weight);

        $weight = (new WeightMeasurement())
            ->setSubject($subject1)
            ->setWeight(28.5)
            ->setMeasuredAt(new \DateTime('2024-08-07 09:00:00'))
            ->setUser($researcher)
            ->setAccount($account2)
        ;
        $manager->persist($weight);

        $variable = (new Variable())
            ->setName('Activity Level')
            ->setAccount($account2)
        ;
        $manager->persist($variable);

        $timeSeries = (new TimeSeries())
            ->setExperiment($experiment)
            ->setSubject($subject1)
            ->setVariable($variable)
            ->setRecordedAt(new \DateTime('2024-07-05 09:00:00'))
            ->setValue('34.34500')
            ->setAccount($account2)
        ;
        $manager->persist($timeSeries);

        $variable2 = (new Variable())
            ->setName('Food Intake') // Assure-toi que le nom est pertinent
            ->setAccount($account2)
        ;
        $manager->persist($variable2);

        $homeCageMonitoring = (new HomeCageMonitoring())
            ->setExperiment($experiment)
            ->setSystem('HCM-System-X') // Remplace par le bon système si nécessaire
            ->setAccount($account2)
        ;
        $manager->persist($homeCageMonitoring);

        $hcmMeasure = (new CageHcmMeasure())
            ->setCage($cage1)
            ->setValue(8.7)
            ->setVariable($variable2)
            ->setHomeCageMonitoring($homeCageMonitoring) // ✅ Ajoute cette ligne
            ->setRecordedAt(new \DateTime('2024-07-05 09:00:00'))
            ->setDataType(HcmDataType::FoodIntake)
            ->setAccount($account2)
        ;
        $manager->persist($hcmMeasure);

        $manager->flush();
    }
}
