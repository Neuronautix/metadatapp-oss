#!/usr/bin/env python3
"""
fetch-mapp-entities.py

Fetches MAPP entities (Experiments/Studies, Subjects, TimeSeries, Behaviors)
from the MAPP REST API and saves them as JSON-LD files under ./artifact/.

Environment variables:
  MAPP_API_URL   – Base URL of the MAPP API  (e.g. https://api.metadatapp.test)
  MAPP_API_TOKEN – Bearer token for authentication
  EXPERIMENT_ID  – (optional) Single experiment UUID to export; fetches all if blank
"""

from __future__ import annotations

import json
import os
import sys
from pathlib import Path

import requests

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

API_URL = os.environ.get("MAPP_API_URL", "").rstrip("/")
API_TOKEN = os.environ.get("MAPP_API_TOKEN", "")
EXPERIMENT_ID = os.environ.get("EXPERIMENT_ID", "").strip()

if not API_URL:
    print("ERROR: MAPP_API_URL environment variable is not set.", file=sys.stderr)
    sys.exit(1)

if not API_TOKEN:
    print("ERROR: MAPP_API_TOKEN environment variable is not set.", file=sys.stderr)
    sys.exit(1)

ARTIFACT_DIR = Path("artifact")
METADATA_DIR = ARTIFACT_DIR / "metadata"
SUBJECTS_DIR = METADATA_DIR / "subjects"
DATA_DIR = ARTIFACT_DIR / "data"
MEDIA_DIR = ARTIFACT_DIR / "media"
CODE_DIR = ARTIFACT_DIR / "code"

for directory in (METADATA_DIR, SUBJECTS_DIR, DATA_DIR, MEDIA_DIR, CODE_DIR):
    directory.mkdir(parents=True, exist_ok=True)

HEADERS = {
    "Accept": "application/ld+json",
    "Authorization": f"Bearer {API_TOKEN}",
}


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _get(path: str, params: dict | None = None) -> dict:
    """Perform a GET request and return the decoded JSON body."""
    url = f"{API_URL}{path}"
    response = requests.get(url, headers=HEADERS, params=params, timeout=30)
    response.raise_for_status()
    return response.json()


def _without_id(data: dict) -> dict:
    """Return a copy of *data* without the '@id' key (used when setting a new @id)."""
    return {k: v for k, v in data.items() if k != "@id"}


def _save_json(path: Path, data: dict) -> None:
    """Serialize *data* as JSON and write it to *path*."""
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"  saved → {path}")


# ---------------------------------------------------------------------------
# Fetch Experiments
# ---------------------------------------------------------------------------

def fetch_experiments() -> list[dict]:
    """Return a list of experiment JSON-LD objects."""
    if EXPERIMENT_ID:
        experiment = _get(f"/studies/{EXPERIMENT_ID}")
        return [experiment]

    response = _get("/studies")
    members = response.get("hydra:member", response.get("member", [response]))
    return members


# ---------------------------------------------------------------------------
# Fetch related entities for one experiment
# ---------------------------------------------------------------------------

def fetch_subjects(experiment_id: str) -> list[dict]:
    """Fetch all subjects linked to an experiment."""
    try:
        response = _get("/subjects", params={"experiment": experiment_id})
        return response.get("hydra:member", response.get("member", []))
    except requests.HTTPError as exc:
        print(f"  WARNING: could not fetch subjects for {experiment_id}: {exc}", file=sys.stderr)
        return []


def fetch_timeseries(experiment_id: str) -> list[dict]:
    """Fetch all time-series records for an experiment."""
    try:
        response = _get("/time_series", params={"experiment": experiment_id})
        return response.get("hydra:member", response.get("member", []))
    except requests.HTTPError as exc:
        print(f"  WARNING: could not fetch time series for {experiment_id}: {exc}", file=sys.stderr)
        return []


def fetch_behaviors(experiment_id: str) -> list[dict]:
    """Fetch all behavior records for an experiment."""
    try:
        response = _get("/behaviors", params={"experiment": experiment_id})
        return response.get("hydra:member", response.get("member", []))
    except requests.HTTPError as exc:
        print(f"  WARNING: could not fetch behaviors for {experiment_id}: {exc}", file=sys.stderr)
        return []


# ---------------------------------------------------------------------------
# Process each experiment
# ---------------------------------------------------------------------------

def process_experiment(experiment: dict) -> None:
    raw_id: str = experiment.get("id") or experiment.get("@id", "unknown")
    # Strip IRI prefix to obtain a plain UUID/slug
    experiment_id = raw_id.rsplit("/", 1)[-1]

    print(f"\nProcessing experiment: {experiment_id}")

    # Save experiment metadata
    _save_json(METADATA_DIR / f"experiment-{experiment_id}.jsonld", experiment)

    # ---- Subjects --------------------------------------------------------
    subjects = fetch_subjects(experiment_id)
    print(f"  {len(subjects)} subject(s) found")
    for subject in subjects:
        subject_id = str(subject.get("id") or subject.get("@id", "unknown")).rsplit("/", 1)[-1]
        _save_json(SUBJECTS_DIR / f"subject-{subject_id}.jsonld", subject)

    # ---- TimeSeries -------------------------------------------------------
    timeseries_list = fetch_timeseries(experiment_id)
    print(f"  {len(timeseries_list)} time-series record(s) found")
    for ts in timeseries_list:
        ts_id = str(ts.get("id") or ts.get("@id", "unknown")).rsplit("/", 1)[-1]

        # Save full JSON-LD metadata stub
        ts_stub = {
            "@id": f"data/timeseries-{ts_id}.jsonld",
            "@type": "File",
            "name": f"timeseries-{ts_id}.jsonld",
            "encodingFormat": "application/ld+json",
            **_without_id(ts),
        }
        _save_json(DATA_DIR / f"timeseries-{ts_id}.jsonld", ts_stub)

    # ---- Behaviors --------------------------------------------------------
    behaviors = fetch_behaviors(experiment_id)
    print(f"  {len(behaviors)} behavior(s) found")
    for behavior in behaviors:
        behavior_id = str(behavior.get("id") or behavior.get("@id", "unknown")).rsplit("/", 1)[-1]

        # Capture protocolUri for use as a `url` provenance link.
        protocol_uri: str = behavior.get("protocolUri", "")

        # The stub is a JSON-LD description saved as a .jsonld file.
        # Its @id and name reflect the file on disk (behavior-<id>.jsonld).
        # If the behavior has a protocolUri pointing to the raw media file,
        # it is recorded as the `url` property for provenance.
        stub = {
            "@id": f"media/behavior-{behavior_id}.jsonld",
            "@type": "File",
            "name": f"behavior-{behavior_id}.jsonld",
            "encodingFormat": "application/ld+json",
            **_without_id(behavior),
        }
        if protocol_uri:
            stub["url"] = protocol_uri
        _save_json(MEDIA_DIR / f"behavior-{behavior_id}.jsonld", stub)

    # ---- Repository / code -----------------------------------------------
    repo_link: str = experiment.get("repositoryLink") or ""
    if repo_link:
        code_stub = {
            "@id": f"code/analysis-{experiment_id}",
            "@type": "SoftwareSourceCode",
            "name": f"analysis-{experiment_id}",
            "codeRepository": repo_link,
        }
        _save_json(CODE_DIR / f"analysis-{experiment_id}.jsonld", code_stub)


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def main() -> None:
    print(f"Fetching MAPP entities from {API_URL}")
    experiments = fetch_experiments()
    print(f"Found {len(experiments)} experiment(s)")

    for experiment in experiments:
        process_experiment(experiment)

    print("\nDone fetching MAPP entities.")


if __name__ == "__main__":
    main()
