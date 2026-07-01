#!/usr/bin/env python3
"""Inspect an NWB file and emit normalized JSON metadata for Metadatapp."""

from __future__ import annotations

import json
import sys
from pathlib import Path

try:
    from pynwb import NWBHDF5IO
except ImportError as exc:  # pragma: no cover - depends on optional runtime package
    raise SystemExit("PyNWB is not installed. Install api/tools/nwb/requirements.txt.") from exc


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: import_nwb.py <input.nwb> <output.json>", file=sys.stderr)
        return 2

    input_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])

    with NWBHDF5IO(str(input_path), "r", load_namespaces=True) as io:
        nwbfile = io.read()
        payload = {
            "identifier": nwbfile.identifier,
            "sessionDescription": nwbfile.session_description,
            "sessionStartTime": nwbfile.session_start_time.isoformat() if nwbfile.session_start_time else None,
            "experimentDescription": nwbfile.experiment_description,
            "notes": nwbfile.notes,
            "acquisitionCount": len(nwbfile.acquisition),
            "processingModules": list(nwbfile.processing.keys()),
            "subject": None if nwbfile.subject is None else {
                "subjectId": nwbfile.subject.subject_id,
                "species": nwbfile.subject.species,
                "sex": nwbfile.subject.sex,
                "description": nwbfile.subject.description,
            },
        }

    output_path.write_text(json.dumps(payload, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
