# FAIR3R Exchange Schemas

This directory keeps the contracts used for data exchange and validation between
Metadatapp and FAIR3R.

- `fair3r-wizard.schema.json` is the full FAIR3R Open Science Dataset
  Registration Wizard schema. It is the canonical guide for FDF fields,
  ontology lookups, conditional sections, and declarative output mappings.
- `fair3r-fdf-dataset.schema.json` is the generated DataCite JSON subset that
  Metadatapp emits and validates through the `/fair3r/.../dataset.json` and
  `/fair3r/datasets/validate` endpoints.
- `fair3r-datacite-doi-example.xml` is the FAIR3R/DataCite DOI XML reference
  used to guide Metadatapp's `/fair3r/.../datacite.xml` output.

When FAIR3R changes the wizard contract, update `fair3r-wizard.schema.json`
first, then adjust the Metadatapp mapper and the generated exchange schema to
match the relevant output fields.
