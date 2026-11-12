<?php

declare(strict_types=1);

namespace App\Service;

final class Fair3rDemoCsvCatalog
{
    /**
     * @return array<string, array{filename: string, title: string, description: string, csv: string}>
     */
    public function all(): array
    {
        return [
            'bodyweight-longitudinal' => [
                'filename' => 'bodyweight-longitudinal.csv',
                'title' => 'Bodyweight longitudinal results',
                'description' => 'Weekly bodyweight measurements for young, middle-aged, and aged cohorts.',
                'csv' => <<<'CSV'
subject_id,cohort,sex,age_group,week,bodyweight_g
MUS-001,control,F,young,0,22.4
MUS-001,control,F,young,1,23.1
MUS-001,control,F,young,2,23.8
MUS-002,treatment,M,young,0,24.6
MUS-002,treatment,M,young,1,25.2
MUS-002,treatment,M,young,2,25.7
MUS-101,control,F,middle-aged,0,30.9
MUS-101,control,F,middle-aged,1,31.4
MUS-101,control,F,middle-aged,2,31.8
MUS-201,treatment,M,aged,0,35.6
MUS-201,treatment,M,aged,1,36.0
MUS-201,treatment,M,aged,2,36.2
CSV,
            ],
            'open-field-behavior' => [
                'filename' => 'open-field-behavior.csv',
                'title' => 'Open field behavioral assay',
                'description' => 'Distance, center time, and rearing counts from an open field test.',
                'csv' => <<<'CSV'
subject_id,cohort,age_group,total_distance_m,center_time_s,rearing_count,anxiety_index
MUS-001,control,young,42.8,118,34,0.28
MUS-002,treatment,young,39.5,104,29,0.33
MUS-101,control,middle-aged,31.7,82,20,0.44
MUS-102,treatment,middle-aged,35.1,96,24,0.38
MUS-201,control,aged,24.3,61,12,0.57
MUS-202,treatment,aged,28.9,74,16,0.49
CSV,
            ],
            'novel-object-recognition' => [
                'filename' => 'novel-object-recognition.csv',
                'title' => 'Novel object recognition assay',
                'description' => 'Exploration times and discrimination index for recognition memory.',
                'csv' => <<<'CSV'
subject_id,cohort,age_group,familiar_object_s,novel_object_s,total_exploration_s,discrimination_index
MUS-001,control,young,18.2,35.6,53.8,0.32
MUS-002,treatment,young,20.1,33.4,53.5,0.25
MUS-101,control,middle-aged,24.9,28.2,53.1,0.06
MUS-102,treatment,middle-aged,21.5,32.0,53.5,0.20
MUS-201,control,aged,27.6,25.9,53.5,-0.03
MUS-202,treatment,aged,24.1,30.7,54.8,0.12
CSV,
            ],
            'plasma-cytokine-assay' => [
                'filename' => 'plasma-cytokine-assay.csv',
                'title' => 'Plasma cytokine assay',
                'description' => 'Multiplex plasma cytokine concentrations measured in pg/mL.',
                'csv' => <<<'CSV'
subject_id,cohort,age_group,il6_pg_ml,tnfa_pg_ml,il10_pg_ml,ifng_pg_ml
MUS-001,control,young,4.2,7.1,12.4,3.8
MUS-002,treatment,young,3.9,6.8,13.1,3.5
MUS-101,control,middle-aged,7.6,10.2,9.8,5.7
MUS-102,treatment,middle-aged,6.1,8.9,11.2,4.9
MUS-201,control,aged,12.4,16.8,7.1,8.6
MUS-202,treatment,aged,9.8,13.3,8.6,7.2
CSV,
            ],
        ];
    }

    /**
     * @return array{filename: string, title: string, description: string, csv: string}|null
     */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }
}
