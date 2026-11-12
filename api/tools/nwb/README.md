# NWB Import/Export Tools

This directory contains the optional Python boundary used by the Symfony NWB
controllers:

- `export_nwb.py` converts Metadatapp experiment metadata JSON into `.nwb`.
- `import_nwb.py` inspects an uploaded `.nwb` file and returns normalized JSON.

Install the runtime dependency in the API environment when NWB routes are
enabled:

```bash
python3 -m pip install -r api/tools/nwb/requirements.txt
```

No binary NWB fixtures are stored in the repository.
