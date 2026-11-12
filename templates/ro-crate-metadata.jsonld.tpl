{
  "@context": [
    "https://w3id.org/ro/crate/1.1/context",
    {
      "mapp": "https://schema.org/",
      "schema": "https://schema.org/",
      "Experiment": "schema:MedicalStudy",
      "Study": "schema:MedicalStudy",
      "Project": "schema:ResearchProject",
      "Investigation": "schema:ResearchProject",
      "Subject": "schema:Patient",
      "TimeSeries": "schema:Dataset",
      "Behavior": "schema:MedicalProcedure",
      "Procedure": "schema:MedicalProcedure",
      "goal": "schema:description",
      "protocol": "schema:measurementTechnique",
      "startAt": "schema:startDate",
      "endAt": "schema:endDate",
      "repositoryLink": "schema:codeRepository",
      "externalId": "schema:identifier",
      "protocolUri": "schema:url",
      "species": "schema:taxonomicRange",
      "strain": "schema:additionalType"
    }
  ],
  "@graph": [
    {
      "@id": "ro-crate-metadata.json",
      "@type": "CreativeWork",
      "conformsTo": {"@id": "https://w3id.org/ro/crate/1.1"},
      "about": {"@id": "./"}
    },
    {
      "@id": "./",
      "@type": "Dataset",
      "name": "{{EXPERIMENT_NAME}}",
      "description": "{{EXPERIMENT_DESCRIPTION}}",
      "creator": {"@id": "#creator"},
      "dateCreated": "{{DATE_CREATED}}",
      "dateModified": "{{DATE_MODIFIED}}",
      "url": "{{EXPERIMENT_URL}}",
      "identifier": "{{EXPERIMENT_ID}}",
      "keywords": ["MAPP", "neuroscience", "metadata"],
      "license": {"@id": "https://creativecommons.org/licenses/by/4.0/"},
      "hasPart": []
    },
    {
      "@id": "#creator",
      "@type": "Person",
      "name": "{{CREATOR_NAME}}",
      "email": "{{CREATOR_EMAIL}}"
    }
  ]
}
