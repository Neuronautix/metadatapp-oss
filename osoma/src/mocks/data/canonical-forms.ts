import type {
  CanonicalForm,
  CanonicalFormField,
  CanonicalFormSectionRef,
} from '@/features/hcm-metadata-form/hcm-metadata-form.types.ts'

// Stable deterministic UUID-ish ids so detail lookups by id work in mock mode.
const HCM_FORM_ID = '00000000-0000-4000-8000-000000000001'

const sectionId = (n: number) => `00000000-0000-4000-8001-0000000000${String(n).padStart(2, '0')}`
const fieldId = (n: number) => `00000000-0000-4000-8002-0000000000${String(n).padStart(2, '0')}`

const section = (
  n: number,
  title: string,
  description: string,
  orderIndex: number
): CanonicalFormSectionRef => ({
  id: sectionId(n),
  title,
  description,
  orderIndex,
})

type FieldSeed = Omit<CanonicalFormField, 'id' | 'section' | 'orderIndex'>

const buildSection = (
  ref: CanonicalFormSectionRef,
  seeds: FieldSeed[],
  startIndex: number
): { fields: CanonicalFormField[]; nextIndex: number } => {
  const fields = seeds.map((seed, idx) => ({
    ...seed,
    id: fieldId(startIndex + idx),
    section: ref,
    orderIndex: idx,
  }))
  return { fields, nextIndex: startIndex + seeds.length }
}

const study = section(1, 'Study', 'Top-level study identification, objectives, design, and ethical authorisation.', 0)
const animals = section(2, 'Animals', 'Biological characteristics of the experimental animals.', 1)
const cohort = section(3, 'Cohort', 'Cohort and batching structure, group assignment, and randomisation.', 2)
const housing = section(4, 'Housing', 'Cage and environmental housing conditions during the study.', 3)
const hcmDevice = section(5, 'HCM Device', 'The Home Cage Monitoring hardware and software configuration.', 4)
const recording = section(6, 'Recording Protocol', 'Habituation, recording window, interventions, and disturbance events.', 5)
const behavioral = section(7, 'Behavioral Metrics', 'Definitions of the behavioural metrics derived from HCM data.', 6)
const dataReuse = section(8, 'Data Reuse', 'Availability, licensing, and export format of study data for FAIR reuse.', 7)

const studyFields = buildSection(study, [
  {
    localKey: 'study_identifier', label: 'Study identifier',
    description: 'A unique identifier assigned to the HCM study.',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.org/dc/terms/identifier', label: 'identifier', source: 'DCTERMS' }],
    jsonldPath: null, sourceFieldReference: 'cde:study_identifier',
  },
  {
    localKey: 'experimental_objective', label: 'Experimental objective',
    description: 'The scientific objective or hypothesis the study is designed to address.',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/OBI_0000441', label: 'objective specification', source: 'OBI' }],
    jsonldPath: null, sourceFieldReference: 'cde:experimental_objective',
  },
  {
    localKey: 'experimental_design', label: 'Experimental design',
    description: 'The overall experimental design (e.g. between-subjects, within-subjects, longitudinal, crossover).',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/OBI_0500000', label: 'study design', source: 'OBI' }],
    jsonldPath: null, sourceFieldReference: 'cde:experimental_design',
  },
  {
    localKey: 'ethical_authorization', label: 'Ethical authorization',
    description: 'Identifier or reference for the ethical/animal-welfare authorisation governing the study.',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [],
    jsonldPath: null, sourceFieldReference: 'cde:ethical_authorization',
  },
], 1)

const animalsFields = buildSection(animals, [
  {
    localKey: 'species', label: 'Species',
    description: 'The taxonomic species of the experimental animals.',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [
      { value: 'Mus musculus', ontologyTerm: 'http://purl.obolibrary.org/obo/NCBITaxon_10090' },
      { value: 'Rattus norvegicus', ontologyTerm: 'http://purl.obolibrary.org/obo/NCBITaxon_10116' },
    ],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/OBI_0100026', label: 'organism', source: 'OBI' }],
    jsonldPath: null, sourceFieldReference: 'cde:species',
  },
  {
    localKey: 'strain', label: 'Strain',
    description: 'The genetic strain or stock of the experimental animals.',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [{ value: 'C57BL/6J' }, { value: 'BALB/c' }, { value: 'Wistar' }],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C14419', label: 'Strain', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:strain',
  },
  {
    localKey: 'genotype', label: 'Genotype',
    description: 'The genotype of the experimental animals, including any genetic modifications.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://www.ebi.ac.uk/efo/EFO_0000513', label: 'genotype', source: 'EFO' }],
    jsonldPath: null, sourceFieldReference: 'cde:genotype',
  },
  {
    localKey: 'sex', label: 'Sex',
    description: 'The biological sex of the experimental animals.',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [
      { value: 'male', ontologyTerm: 'http://purl.obolibrary.org/obo/PATO_0000384' },
      { value: 'female', ontologyTerm: 'http://purl.obolibrary.org/obo/PATO_0000383' },
    ],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/PATO_0000047', label: 'biological sex', source: 'PATO' }],
    jsonldPath: null, sourceFieldReference: 'cde:sex',
  },
  {
    localKey: 'age_at_recording', label: 'Age at recording',
    description: 'The age of the animals at the start of HCM recording.',
    datatype: 'decimal', required: true, unitConstraint: 'weeks',
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/PATO_0000011', label: 'age', source: 'PATO' }],
    jsonldPath: null, sourceFieldReference: 'cde:age_at_recording',
  },
  {
    localKey: 'body_weight', label: 'Body weight',
    description: 'The body weight of the animals at the start of HCM recording.',
    datatype: 'decimal', required: false, unitConstraint: 'g',
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C81328', label: 'Body Weight', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:body_weight',
  },
], studyFields.nextIndex)

const cohortFields = buildSection(cohort, [
  {
    localKey: 'cohort_id', label: 'Cohort ID',
    description: 'Identifier for the cohort of animals studied together.',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C61512', label: 'Cohort', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:cohort_id',
  },
  {
    localKey: 'batch_id', label: 'Batch ID',
    description: 'Identifier for the experimental batch within the cohort.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C67073', label: 'Batch', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:batch_id',
  },
  {
    localKey: 'group_assignment', label: 'Group assignment',
    description: 'The experimental group the animals were assigned to (e.g. control, treatment).',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C73464', label: 'Study Group', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:group_assignment',
  },
  {
    localKey: 'randomization_method', label: 'Randomization method',
    description: 'The method used to randomise animals to groups.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C25196', label: 'Randomization', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:randomization_method',
  },
], animalsFields.nextIndex)

const housingFields = buildSection(housing, [
  {
    localKey: 'cage_type', label: 'Cage type',
    description: 'The type of cage used to house the animals (e.g. IVC, open-top).',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C172264', label: 'Animal Housing', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:cage_type',
  },
  {
    localKey: 'animals_per_cage', label: 'Animals per cage',
    description: 'The number of animals housed per cage.',
    datatype: 'integer', required: true, unitConstraint: null,
    allowedValues: [], ontologyMappings: [],
    jsonldPath: null, sourceFieldReference: 'cde:animals_per_cage',
  },
  {
    localKey: 'bedding', label: 'Bedding',
    description: 'The bedding material used in the cage.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [], ontologyMappings: [],
    jsonldPath: null, sourceFieldReference: 'cde:bedding',
  },
  {
    localKey: 'enrichment', label: 'Enrichment',
    description: 'Environmental enrichment items provided in the cage.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/ENVO_01000934', label: 'environmental enrichment', source: 'ENVO' }],
    jsonldPath: null, sourceFieldReference: 'cde:enrichment',
  },
  {
    localKey: 'light_dark_cycle', label: 'Light/dark cycle',
    description: 'The light/dark cycle the animals were maintained under (e.g. 12:12 LD, lights-on time).',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C82379', label: 'Light Dark Cycle', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:light_dark_cycle',
  },
  {
    localKey: 'temperature', label: 'Temperature',
    description: 'The ambient temperature of the housing room.',
    datatype: 'decimal', required: false, unitConstraint: 'Cel',
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C25206', label: 'Temperature', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:temperature',
  },
  {
    localKey: 'humidity', label: 'Humidity',
    description: 'The relative humidity of the housing room.',
    datatype: 'decimal', required: false, unitConstraint: '%',
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C62652', label: 'Humidity', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:humidity',
  },
], cohortFields.nextIndex)

const hcmDeviceFields = buildSection(hcmDevice, [
  {
    localKey: 'device_manufacturer', label: 'Device manufacturer',
    description: 'The manufacturer of the HCM device.',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C25392', label: 'Manufacturer', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:device_manufacturer',
  },
  {
    localKey: 'device_model', label: 'Device model',
    description: 'The model name/number of the HCM device.',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ label: 'home cage monitoring device', source: 'HCMO' }],
    jsonldPath: null, sourceFieldReference: 'cde:device_model',
  },
  {
    localKey: 'sensor_type', label: 'Sensor type',
    description: 'The type of sensor(s) used by the HCM device (e.g. RFID, capacitive, PIR, load cell).',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [
      { value: 'RFID' }, { value: 'capacitance' }, { value: 'passive infrared (PIR)' },
      { value: 'load cell' }, { value: 'video' },
    ],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C50166', label: 'Sensor Device', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:sensor_type',
  },
  {
    localKey: 'camera_configuration', label: 'Camera configuration',
    description: 'The camera configuration used for video-based monitoring (e.g. top-down RGB, IR, resolution).',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [], ontologyMappings: [],
    jsonldPath: null, sourceFieldReference: 'cde:camera_configuration',
  },
  {
    localKey: 'sampling_frequency', label: 'Sampling frequency',
    description: 'The data sampling frequency of the HCM device.',
    datatype: 'decimal', required: true, unitConstraint: 'Hz',
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C73408', label: 'Sampling Frequency', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:sampling_frequency',
  },
  {
    localKey: 'software_version', label: 'Software version',
    description: 'The version of the HCM acquisition/analysis software.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C111093', label: 'Software Version', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:software_version',
  },
], housingFields.nextIndex)

const recordingFields = buildSection(recording, [
  {
    localKey: 'habituation_duration', label: 'Habituation duration',
    description: 'The duration animals were habituated to the HCM cage before recording.',
    datatype: 'decimal', required: false, unitConstraint: 'h',
    allowedValues: [], ontologyMappings: [],
    jsonldPath: null, sourceFieldReference: 'cde:habituation_duration',
  },
  {
    localKey: 'recording_start_end', label: 'Recording start/end',
    description: 'The start and end timestamps of the HCM recording window.',
    datatype: 'datetime', required: true, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C181317', label: 'Recording Period', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:recording_start_end',
  },
  {
    localKey: 'intervention_timing', label: 'Intervention timing',
    description: 'The timing of any interventions (e.g. treatment, surgery) relative to recording.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C25218', label: 'Intervention', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:intervention_timing',
  },
  {
    localKey: 'cleaning_disturbance_events', label: 'Cleaning/disturbance events',
    description: 'Recorded cage cleaning or disturbance events during the recording window.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [], ontologyMappings: [],
    jsonldPath: null, sourceFieldReference: 'cde:cleaning_disturbance_events',
  },
], hcmDeviceFields.nextIndex)

const behavioralFields = buildSection(behavioral, [
  {
    localKey: 'activity_metric_definition', label: 'Activity metric definition',
    description: 'Definition and method for the locomotor activity metric.',
    datatype: 'string', required: true, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C61345', label: 'Locomotor Activity', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:activity_metric_definition',
  },
  {
    localKey: 'sleep_rest_definition', label: 'Sleep/rest definition',
    description: 'Definition and method for the sleep/rest metric (e.g. immobility threshold).',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C94483', label: 'Sleep', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:sleep_rest_definition',
  },
  {
    localKey: 'feeding_metric_definition', label: 'Feeding metric definition',
    description: 'Definition and method for the feeding/food-intake metric.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C84444', label: 'Food Intake', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:feeding_metric_definition',
  },
  {
    localKey: 'social_proximity_definition', label: 'Social proximity definition',
    description: 'Definition and method for the social proximity / interaction metric.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C17005', label: 'Social Behavior', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:social_proximity_definition',
  },
  {
    localKey: 'circadian_aggregation_method', label: 'Circadian aggregation method',
    description: 'Method used to aggregate metrics over the circadian cycle (e.g. cosinor, hourly binning).',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [
      { value: 'cosinor' }, { value: 'hourly binning' }, { value: 'light/dark phase averaging' },
    ],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/GO_0007623', label: 'circadian rhythm', source: 'GO' }],
    jsonldPath: null, sourceFieldReference: 'cde:circadian_aggregation_method',
  },
], recordingFields.nextIndex)

const dataReuseFields = buildSection(dataReuse, [
  {
    localKey: 'raw_data_available', label: 'Raw data available?',
    description: 'Whether raw HCM data is available for reuse.',
    datatype: 'boolean', required: true, unitConstraint: null,
    allowedValues: [], ontologyMappings: [],
    jsonldPath: null, sourceFieldReference: 'cde:raw_data_available',
  },
  {
    localKey: 'processed_data_available', label: 'Processed data available?',
    description: 'Whether processed/derived HCM data is available for reuse.',
    datatype: 'boolean', required: true, unitConstraint: null,
    allowedValues: [], ontologyMappings: [],
    jsonldPath: null, sourceFieldReference: 'cde:processed_data_available',
  },
  {
    localKey: 'code_available', label: 'Code available?',
    description: 'Whether analysis code is available for reuse.',
    datatype: 'boolean', required: false, unitConstraint: null,
    allowedValues: [], ontologyMappings: [],
    jsonldPath: null, sourceFieldReference: 'cde:code_available',
  },
  {
    localKey: 'doi', label: 'DOI',
    description: 'The Digital Object Identifier of the dataset.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [],
    ontologyMappings: [{ iri: 'http://purl.org/ontology/bibo/doi', label: 'doi', source: 'BIBO' }],
    jsonldPath: null, sourceFieldReference: 'cde:doi',
  },
  {
    localKey: 'license', label: 'License',
    description: 'The license under which the dataset is released.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [
      { value: 'CC BY 4.0', ontologyTerm: 'https://creativecommons.org/licenses/by/4.0/' },
      { value: 'CC0 1.0', ontologyTerm: 'https://creativecommons.org/publicdomain/zero/1.0/' },
    ],
    ontologyMappings: [{ iri: 'http://purl.org/dc/terms/license', label: 'license', source: 'DCTERMS' }],
    jsonldPath: null, sourceFieldReference: 'cde:license',
  },
  {
    localKey: 'export_format', label: 'Export format',
    description: 'The export/encoding format of the released dataset.',
    datatype: 'string', required: false, unitConstraint: null,
    allowedValues: [
      { value: 'RO-Crate' }, { value: 'ISA-Tab' }, { value: 'FAIR2/Croissant JSON-LD' }, { value: 'CSV' },
    ],
    ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/NCIT_C42883', label: 'File Format', source: 'NCIT' }],
    jsonldPath: null, sourceFieldReference: 'cde:export_format',
  },
], behavioralFields.nextIndex)

const allFields: CanonicalFormField[] = [
  ...studyFields.fields,
  ...animalsFields.fields,
  ...cohortFields.fields,
  ...housingFields.fields,
  ...hcmDeviceFields.fields,
  ...recordingFields.fields,
  ...behavioralFields.fields,
  ...dataReuseFields.fields,
]

export const hcmCanonicalForm: CanonicalForm = {
  '@context': '/contexts/CanonicalForm',
  '@id': `/canonical-forms/${HCM_FORM_ID}`,
  '@type': 'CanonicalForm',
  id: HCM_FORM_ID,
  title: 'HCM Minimal Metadata Form',
  description:
    'Minimal, FAIR-aligned metadata for Home Cage Monitoring (HCM) studies in preclinical research. Captures study, animal, cohort, housing, device, recording, behavioural-metric, and data-reuse metadata as reusable Common Data Elements grouped along the ISA Investigation/Study/Assay hierarchy.',
  domain: 'home-cage-monitoring',
  version: '1.0.0',
  status: 'active',
  sourceLinks: {
    isaMapping: {
      investigation: {
        study: {
          forms: [
            { section: 'study', isaForm: 'Study' },
            { section: 'animals', isaForm: 'Animal Form' },
            { section: 'cohort', isaForm: 'Cohort Form' },
            { section: 'housing', isaForm: 'Housing Form' },
            { section: 'recording_protocol', isaForm: 'Protocol Form' },
          ],
          assay: {
            forms: [
              { section: 'hcm_device', isaForm: 'HCM Device Form' },
              { section: 'recording_protocol', isaForm: 'Recording Form' },
              { section: 'behavioral_metrics', isaForm: 'Behavioral Metrics Form' },
              { section: 'data_reuse', isaForm: 'Data Export Form' },
            ],
          },
        },
      },
    },
  },
  createdAt: '2026-01-15T09:00:00Z',
  updatedAt: '2026-01-15T09:00:00Z',
  sections: [study, animals, cohort, housing, hcmDevice, recording, behavioral, dataReuse],
  fields: allFields,
}

export const canonicalForms: CanonicalForm[] = [hcmCanonicalForm]
