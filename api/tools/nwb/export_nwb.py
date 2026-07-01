#!/usr/bin/env python3
"""Export Metadatapp experiment metadata to an NWB file."""

from __future__ import annotations

import json
import sys
from datetime import datetime, timezone
from pathlib import Path

try:
    from pynwb import NWBFile, NWBHDF5IO
except ImportError as exc:  # pragma: no cover - depends on optional runtime package
    raise SystemExit("PyNWB is not installed. Install api/tools/nwb/requirements.txt.") from exc


def parse_datetime(value: str | None) -> datetime:
    if not value:
        return datetime.now(timezone.utc)
    normalized = value.replace("Z", "+00:00")
    return datetime.fromisoformat(normalized)


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: export_nwb.py <metadata.json> <output.nwb>", file=sys.stderr)
        return 2

    metadata_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])
    metadata = json.loads(metadata_path.read_text())

    identifier = metadata.get("identifier") or metadata.get("externalId") or "metadatapp-experiment"
    session_description = metadata.get("description") or metadata.get("name") or "Metadatapp experiment"
    session_start_time = parse_datetime(metadata.get("startAt"))

    nwbfile = NWBFile(
        session_description=session_description,
        identifier=str(identifier),
        session_start_time=session_start_time,
        experiment_description=metadata.get("goal"),
        notes=metadata.get("protocol"),
    )

    nwbfile.add_scratch({
        "name": metadata.get("name"),
        "repositoryLink": metadata.get("repositoryLink"),
        "externalId": metadata.get("externalId"),
        "project": metadata.get("project"),
        "procedures": metadata.get("procedures", []),
        "connectedResources": metadata.get("connectedResources", []),
    }, name="metadatapp", description="Metadatapp experiment metadata")

    with NWBHDF5IO(str(output_path), "w") as io:
        io.write(nwbfile)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
