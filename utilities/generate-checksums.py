#!/usr/bin/env python3
"""
generate-checksums.py

Computes SHA-256 checksums and content sizes for every file under artifact/
and injects the values into the corresponding @graph entries of
artifact/ro-crate-metadata.json.

Run this script AFTER assemble-ro-crate.py.
"""

from __future__ import annotations

import hashlib
import json
from pathlib import Path

ARTIFACT_DIR = Path("artifact")
METADATA_PATH = ARTIFACT_DIR / "ro-crate-metadata.json"

# Exclude the metadata file itself from checksumming
EXCLUDED = {"ro-crate-metadata.json"}


def sha256_of(path: Path) -> str:
    """Return the hex SHA-256 digest of a file."""
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()


def main() -> None:
    if not METADATA_PATH.exists():
        print(f"ERROR: {METADATA_PATH} not found. Run assemble-ro-crate.py first.")
        raise SystemExit(1)

    metadata = json.loads(METADATA_PATH.read_text(encoding="utf-8"))
    graph: list[dict] = metadata.get("@graph", [])

    # Build a lookup from @id → graph entry for quick updates
    entry_by_id: dict[str, dict] = {e["@id"]: e for e in graph}

    updated = 0
    for path in sorted(ARTIFACT_DIR.rglob("*")):
        if not path.is_file():
            continue

        rel = path.relative_to(ARTIFACT_DIR).as_posix()
        if rel in EXCLUDED:
            continue

        checksum = sha256_of(path)
        size = str(path.stat().st_size)

        if rel in entry_by_id:
            entry = entry_by_id[rel]
            entry["contentSize"] = size
            entry["sha256"] = checksum
            updated += 1
        else:
            # File exists on disk but has no @graph entry yet — add a minimal one
            new_entry = {
                "@id": rel,
                "@type": "File",
                "name": path.name,
                "contentSize": size,
                "sha256": checksum,
            }
            graph.append(new_entry)
            entry_by_id[rel] = new_entry
            updated += 1

    metadata["@graph"] = graph
    METADATA_PATH.write_text(
        json.dumps(metadata, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )
    print(f"Checksums updated for {updated} file(s) → {METADATA_PATH}")


if __name__ == "__main__":
    main()
