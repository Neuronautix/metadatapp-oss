#!/usr/bin/env python3
"""
assemble-ro-crate.py

Assembles a valid `ro-crate-metadata.json` file by:
  1. Loading the skeleton template from templates/ro-crate-metadata.jsonld.tpl
  2. Reading experiment metadata from artifact/metadata/experiment-*.jsonld
  3. Scanning all files under artifact/ and adding them to the @graph
  4. Writing artifact/ro-crate-metadata.json

Expected directory layout (produced by fetch-mapp-entities.py):
  artifact/
    metadata/
      experiment-<ID>.jsonld
      subjects/
        subject-<ID>.jsonld
    data/
      timeseries-<ID>.jsonld
    media/
      behavior-<ID>.jsonld
    code/
      analysis-<ID>.jsonld
"""

from __future__ import annotations

import copy
import json
import mimetypes
import os
import sys
from datetime import datetime, timezone
from pathlib import Path

# ---------------------------------------------------------------------------
# Paths
# ---------------------------------------------------------------------------

REPO_ROOT = Path(__file__).resolve().parent.parent
TEMPLATE_PATH = REPO_ROOT / "templates" / "ro-crate-metadata.jsonld.tpl"
ARTIFACT_DIR = Path("artifact")
OUTPUT_PATH = ARTIFACT_DIR / "ro-crate-metadata.json"

# Subdirectories that hold file content (not already covered by the top-level
# metadata files that describe themselves).
CONTENT_DIRS = [
    ARTIFACT_DIR / "metadata",
    ARTIFACT_DIR / "data",
    ARTIFACT_DIR / "media",
    ARTIFACT_DIR / "code",
]

MIME_OVERRIDES: dict[str, str] = {
    ".jsonld": "application/ld+json",
    ".json": "application/json",
    ".csv": "text/csv",
    ".tsv": "text/tab-separated-values",
    ".ipynb": "application/x-ipynb+json",
    ".py": "text/x-python",
    ".sh": "text/x-shellscript",
    ".md": "text/markdown",
}


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _now_iso() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def _mime(path: Path) -> str:
    ext = path.suffix.lower()
    if ext in MIME_OVERRIDES:
        return MIME_OVERRIDES[ext]
    guessed, _ = mimetypes.guess_type(str(path))
    return guessed or "application/octet-stream"


def _relative_to_artifact(path: Path) -> str:
    """Return a path relative to the artifact/ directory with forward slashes."""
    return path.relative_to(ARTIFACT_DIR).as_posix()


def _file_entry(path: Path) -> dict:
    """Build a minimal RO-Crate file entity for a given path."""
    rel = _relative_to_artifact(path)
    return {
        "@id": rel,
        "@type": "File",
        "name": path.name,
        "encodingFormat": _mime(path),
        "contentSize": str(path.stat().st_size),
    }


# ---------------------------------------------------------------------------
# Load template
# ---------------------------------------------------------------------------

def load_template() -> dict:
    if not TEMPLATE_PATH.exists():
        raise FileNotFoundError(f"Template not found: {TEMPLATE_PATH}")
    return json.loads(TEMPLATE_PATH.read_text(encoding="utf-8"))


# ---------------------------------------------------------------------------
# Read experiment metadata
# ---------------------------------------------------------------------------

def read_experiment_metadata() -> dict:
    """Return the single experiment JSON-LD found under artifact/metadata/.

    Raises SystemExit if more than one experiment file is present, because a
    single RO-Crate should describe exactly one experiment. Re-run with the
    EXPERIMENT_ID environment variable set to export a specific experiment.
    """
    metadata_dir = ARTIFACT_DIR / "metadata"
    experiment_files = sorted(metadata_dir.glob("experiment-*.jsonld"))

    if not experiment_files:
        return {}

    if len(experiment_files) > 1:
        names = [f.name for f in experiment_files]
        print(
            "ERROR: Multiple experiment files found in artifact/metadata/:\n  "
            + "\n  ".join(names)
            + "\nA single RO-Crate must describe exactly one experiment. "
            "Re-run fetch-mapp-entities.py with EXPERIMENT_ID set to the "
            "ID of the experiment you want to export.",
            file=sys.stderr,
        )
        sys.exit(1)

    return json.loads(experiment_files[0].read_text(encoding="utf-8"))


# ---------------------------------------------------------------------------
# Collect all content files
# ---------------------------------------------------------------------------

def collect_files() -> list[Path]:
    """Return all regular files under CONTENT_DIRS, sorted."""
    files: list[Path] = []
    for directory in CONTENT_DIRS:
        if directory.exists():
            files.extend(sorted(p for p in directory.rglob("*") if p.is_file()))
    return files


# ---------------------------------------------------------------------------
# Assemble
# ---------------------------------------------------------------------------

def assemble() -> dict:
    template = load_template()
    experiment = read_experiment_metadata()
    all_files = collect_files()
    now = _now_iso()

    # Build a lookup for existing @graph entries from the template
    graph: list[dict] = template.get("@graph", [])

    # Find the root Dataset entry (the crate root)
    root_entry: dict | None = None
    for entry in graph:
        if entry.get("@id") == "./":
            root_entry = entry
            break

    if root_entry is None:
        root_entry = {"@id": "./", "@type": "Dataset", "hasPart": []}
        graph.append(root_entry)

    # Populate root Dataset fields from the fetched experiment
    if experiment:
        exp_id = str(experiment.get("id") or experiment.get("@id", "")).rsplit("/", 1)[-1]
        root_entry["name"] = experiment.get("name", root_entry.get("name", "MAPP Export"))
        root_entry["description"] = experiment.get("description") or experiment.get("goal") or ""
        root_entry["dateCreated"] = experiment.get("startAt", _now_iso())
        root_entry["dateModified"] = experiment.get("endAt") or _now_iso()
        root_entry["identifier"] = exp_id

        creator_entry = next((e for e in graph if e.get("@id") == "#creator"), None)
        if creator_entry:
            user = experiment.get("user") or {}
            creator_entry["name"] = (
                user.get("name")
                or user.get("displayName")
                or "MAPP User"
            )
    else:
        root_entry.setdefault("name", "MAPP Export")
        root_entry.setdefault("dateCreated", _now_iso())
        root_entry.setdefault("dateModified", _now_iso())

    # Replace any remaining template placeholder strings across all graph entries
    placeholder_defaults: dict[str, str] = {
        "{{EXPERIMENT_NAME}}": "MAPP Export",
        "{{EXPERIMENT_DESCRIPTION}}": "",
        "{{DATE_CREATED}}": now,
        "{{DATE_MODIFIED}}": now,
        "{{EXPERIMENT_URL}}": "",
        "{{EXPERIMENT_ID}}": "",
        "{{CREATOR_NAME}}": "MAPP User",
        "{{CREATOR_EMAIL}}": "",
    }
    for entry in graph:
        for key, val in list(entry.items()):
            if isinstance(val, str) and val in placeholder_defaults:
                entry[key] = placeholder_defaults[val]

    # Build hasPart references and file @graph entries
    has_part: list[dict] = []
    existing_ids = {e["@id"] for e in graph}

    for file_path in all_files:
        rel = _relative_to_artifact(file_path)
        has_part.append({"@id": rel})

        if rel not in existing_ids:
            graph.append(_file_entry(file_path))
            existing_ids.add(rel)

    root_entry["hasPart"] = has_part

    metadata: dict = copy.deepcopy(template)
    metadata["@graph"] = graph
    return metadata


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def main() -> None:
    ARTIFACT_DIR.mkdir(parents=True, exist_ok=True)

    print("Assembling ro-crate-metadata.json …")
    crate_metadata = assemble()

    OUTPUT_PATH.write_text(
        json.dumps(crate_metadata, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )
    print(f"Written → {OUTPUT_PATH}")
    print(f"  @graph entries: {len(crate_metadata.get('@graph', []))}")


if __name__ == "__main__":
    main()
