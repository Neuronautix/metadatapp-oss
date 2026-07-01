<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\GuidelinesHub;

final readonly class GuidelinesHubDataProvider
{
    /**
     * @return list<array{id: string, source: string, sourceLabel: string, title: string, description: string, url: string}>
     */
    public function search(string $query, int $limit): array
    {
        $needle = strtolower($query);
        $results = [];

        foreach ($this->allItems() as $item) {
            if (\count($results) >= $limit) {
                break;
            }
            if ('' === $needle || str_contains(strtolower($item['title'] . ' ' . $item['description']), $needle)) {
                $results[] = $item;
            }
        }

        return $results;
    }

    /** @return list<array{id: string, source: string, sourceLabel: string, title: string, description: string, url: string}> */
    private function allItems(): array
    {
        return array_merge($this->arrive(), $this->prepare(), $this->eqipd());
    }

    /** @return list<array{id: string, source: string, sourceLabel: string, title: string, description: string, url: string}> */
    private function arrive(): array
    {
        $url = 'https://arriveguidelines.org/arrive-guidelines';

        return [
            ['id' => 'arrive:1', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Study design', 'description' => 'For each experiment, provide brief details of the study design including: the groups being compared, and the experimental unit (animal, litter, cage, etc.).', 'url' => $url],
            ['id' => 'arrive:2', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Sample size', 'description' => 'Specify the number of experimental units per group included in the analysis. State if data from any animals or experimental units are not included in the analysis and explain why.', 'url' => $url],
            ['id' => 'arrive:3', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Inclusion and exclusion criteria', 'description' => 'Report any criteria used for including or excluding subjects/experimental units during the experiment, and the number of animals/units excluded.', 'url' => $url],
            ['id' => 'arrive:4', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Randomisation', 'description' => 'State whether and how animals were randomised into groups. If done, describe the method used to generate the randomisation sequence.', 'url' => $url],
            ['id' => 'arrive:5', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Blinding', 'description' => 'State whether the investigators were blinded to the group allocation of the animals during the experiment and/or when assessing outcomes.', 'url' => $url],
            ['id' => 'arrive:6', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Outcome measures', 'description' => 'Clearly define all outcome measures assessed (e.g. cell death, molecular markers, or behavioural changes).', 'url' => $url],
            ['id' => 'arrive:7', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Statistical methods', 'description' => 'Provide details of the statistical methods used for each analysis, and the criteria used to decide whether to adjust for multiple comparisons.', 'url' => $url],
            ['id' => 'arrive:8', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Experimental animals', 'description' => 'Provide species-appropriate details of the animals used, including species, strain and substrain, sex, age or developmental stage, and weight.', 'url' => $url],
            ['id' => 'arrive:9', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Experimental procedures', 'description' => 'For each experimental group, including controls, describe the procedures done to the animals, including any surgical procedures.', 'url' => $url],
            ['id' => 'arrive:10', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Results', 'description' => 'Report the results for each analysis carried out, with a measure of precision (e.g. standard error or confidence interval).', 'url' => $url],
            ['id' => 'arrive:11', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Adverse events', 'description' => 'State any adverse events observed during the study, and report all deaths that occurred.', 'url' => $url],
            ['id' => 'arrive:12', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Discussion', 'description' => 'Interpret the results, taking into account the study objectives and hypotheses, current theory, and other relevant studies in the literature.', 'url' => $url],
            ['id' => 'arrive:13', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Ethical statement', 'description' => 'Provide the name of the ethics committee or institutional review board and indicate whether the project was reviewed and approved.', 'url' => $url],
            ['id' => 'arrive:14', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Study approval', 'description' => 'State the local, national and international regulations and guidelines that the study was conducted under.', 'url' => $url],
            ['id' => 'arrive:15', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Reporting', 'description' => 'Provide the raw data generated in the study. Clearly state where the data are accessible.', 'url' => $url],
            ['id' => 'arrive:16', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Animal welfare', 'description' => 'Describe the welfare-related assessments and interventions that were carried out prior to, during, or after the experiment.', 'url' => $url],
            ['id' => 'arrive:17', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Housing and husbandry', 'description' => 'Provide details of housing and husbandry conditions, including any environmental enrichment.', 'url' => $url],
            ['id' => 'arrive:18', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Sample size calculation', 'description' => 'Specify how the sample size was determined. Where relevant, state: the effect size of interest; the statistical test; the significance threshold; the power; and the number of groups.', 'url' => $url],
            ['id' => 'arrive:19', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Allocation', 'description' => 'Describe how the experimental groups were formed. State the method of allocation and whether it was carried out before or during the study.', 'url' => $url],
            ['id' => 'arrive:20', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Experimental outcomes', 'description' => 'Provide baseline data, where relevant, including baseline characteristics of each group.', 'url' => $url],
            ['id' => 'arrive:21', 'source' => 'arrive', 'sourceLabel' => 'ARRIVE 2.0', 'title' => 'Interpretation/scientific implications', 'description' => 'Comment on whether, and how, the findings of this study are likely to translate to other species or models, and the potential implications for human biology or clinical practice.', 'url' => $url],
        ];
    }

    /** @return list<array{id: string, source: string, sourceLabel: string, title: string, description: string, url: string}> */
    private function prepare(): array
    {
        $url = 'https://nc3rs.org.uk/our-portfolio/prepare';

        return [
            ['id' => 'prepare:1', 'source' => 'prepare', 'sourceLabel' => 'PREPARE', 'title' => 'Relevance and feasibility', 'description' => 'Describe the scientific background and any preliminary data that justify the proposed work.', 'url' => $url],
            ['id' => 'prepare:2', 'source' => 'prepare', 'sourceLabel' => 'PREPARE', 'title' => 'Ethics and legislation', 'description' => 'Ensure the study plan is submitted for review by the institutional animal welfare body.', 'url' => $url],
            ['id' => 'prepare:3', 'source' => 'prepare', 'sourceLabel' => 'PREPARE', 'title' => 'Pilot study', 'description' => 'Consider a pilot study to test methods and refine the experimental design.', 'url' => $url],
            ['id' => 'prepare:4', 'source' => 'prepare', 'sourceLabel' => 'PREPARE', 'title' => 'Study design', 'description' => 'Use the most appropriate experimental design to address the scientific objectives with the minimum number of animals.', 'url' => $url],
            ['id' => 'prepare:5', 'source' => 'prepare', 'sourceLabel' => 'PREPARE', 'title' => 'Statistical analysis plan', 'description' => 'Prepare a detailed statistical analysis plan, including sample size estimation, before starting the study.', 'url' => $url],
            ['id' => 'prepare:6', 'source' => 'prepare', 'sourceLabel' => 'PREPARE', 'title' => 'Experimental animals', 'description' => 'Select and justify the most appropriate animal model, source, and numbers required.', 'url' => $url],
            ['id' => 'prepare:7', 'source' => 'prepare', 'sourceLabel' => 'PREPARE', 'title' => 'Procedures', 'description' => 'Document all procedures in detail, including training requirements, before starting the study.', 'url' => $url],
            ['id' => 'prepare:8', 'source' => 'prepare', 'sourceLabel' => 'PREPARE', 'title' => 'Humane endpoints', 'description' => 'Predetermine humane endpoints and ensure all personnel can recognise them.', 'url' => $url],
        ];
    }

    /** @return list<array{id: string, source: string, sourceLabel: string, title: string, description: string, url: string}> */
    private function eqipd(): array
    {
        $url = 'https://eqipd.eu/resources/';

        return [
            ['id' => 'eqipd:1', 'source' => 'eqipd', 'sourceLabel' => 'EQIPD', 'title' => 'Research question', 'description' => 'Clearly define the research question that the study aims to address.', 'url' => $url],
            ['id' => 'eqipd:2', 'source' => 'eqipd', 'sourceLabel' => 'EQIPD', 'title' => 'Study design', 'description' => 'Describe the study design with reference to the objectives and hypotheses.', 'url' => $url],
            ['id' => 'eqipd:3', 'source' => 'eqipd', 'sourceLabel' => 'EQIPD', 'title' => 'Animal model characterisation', 'description' => 'Describe the animal model and its known characteristics in relation to the research question.', 'url' => $url],
            ['id' => 'eqipd:4', 'source' => 'eqipd', 'sourceLabel' => 'EQIPD', 'title' => 'Data integrity', 'description' => 'Implement appropriate measures to ensure data integrity, including storage and handling.', 'url' => $url],
            ['id' => 'eqipd:5', 'source' => 'eqipd', 'sourceLabel' => 'EQIPD', 'title' => 'Randomisation', 'description' => 'Describe the randomisation process including allocation, concealment, and implementation.', 'url' => $url],
            ['id' => 'eqipd:6', 'source' => 'eqipd', 'sourceLabel' => 'EQIPD', 'title' => 'Blinding', 'description' => 'Describe any blinding applied during the experiment and outcome assessment.', 'url' => $url],
            ['id' => 'eqipd:7', 'source' => 'eqipd', 'sourceLabel' => 'EQIPD', 'title' => 'Outcome measures and analysis', 'description' => 'Pre-specify all outcome measures and statistical analysis methods before data collection.', 'url' => $url],
            ['id' => 'eqipd:8', 'source' => 'eqipd', 'sourceLabel' => 'EQIPD', 'title' => 'Reporting', 'description' => 'Report all outcomes and any deviations from the pre-specified analysis plan.', 'url' => $url],
        ];
    }
}
