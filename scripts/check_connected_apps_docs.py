#!/usr/bin/env python3
"""Keep the Connected Apps user guide in sync with the code.

The authoritative list of integrations is the ``AppCode`` enum in
``api/src/Enum/AppCode.php``. Every code must have a page under
``docs/guide/connecting-apps/<code>.md`` that is non-empty and listed in the
section index's toctree. This mirrors the repo's other drift gates
(``declarations --check``, ``wiki-lint``): the script does not write prose, it
fails CI when a page is missing so an agent or contributor adds it.

Usage:
    python3 scripts/check_connected_apps_docs.py            # check (exit 1 on drift)
    python3 scripts/check_connected_apps_docs.py --list     # print discovered codes

Run from the repository root.
"""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent
ENUM_FILE = REPO_ROOT / "api" / "src" / "Enum" / "AppCode.php"
DOCS_DIR = REPO_ROOT / "docs" / "guide" / "connecting-apps"
INDEX_FILE = DOCS_DIR / "index.md"

# Matches:  case SoftMouse = 'softmouse';
CASE_RE = re.compile(r"case\s+\w+\s*=\s*'([^']+)'\s*;")


def discover_codes() -> list[str]:
    if not ENUM_FILE.is_file():
        sys.exit(f"error: enum file not found: {ENUM_FILE}")
    codes = CASE_RE.findall(ENUM_FILE.read_text(encoding="utf-8"))
    if not codes:
        sys.exit(f"error: no app codes parsed from {ENUM_FILE}")
    return codes


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--list", action="store_true", help="print discovered codes and exit")
    args = parser.parse_args()

    codes = discover_codes()
    if args.list:
        print("\n".join(codes))
        return 0

    index_text = INDEX_FILE.read_text(encoding="utf-8") if INDEX_FILE.is_file() else ""
    problems: list[str] = []

    # 1. Every code has a non-empty page listed in the index toctree.
    for code in codes:
        page = DOCS_DIR / f"{code}.md"
        rel = page.relative_to(REPO_ROOT)
        if not page.is_file():
            problems.append(f"missing page: {rel} (for AppCode '{code}')")
            continue
        if not page.read_text(encoding="utf-8").strip():
            problems.append(f"empty page: {rel}")
        if code not in index_text:
            problems.append(f"page not referenced in {INDEX_FILE.relative_to(REPO_ROOT)} toctree: {code}")

    # 2. No orphan pages (a page with no matching AppCode).
    known = set(codes)
    for page in sorted(DOCS_DIR.glob("*.md")):
        if page.name == "index.md":
            continue
        if page.stem not in known:
            rel = page.relative_to(REPO_ROOT)
            problems.append(f"orphan page (no matching AppCode '{page.stem}'): {rel}")

    if problems:
        print("Connected Apps docs check FAILED:\n")
        for p in problems:
            print(f"  - {p}")
        print(
            "\nEach integration in api/src/Enum/AppCode.php needs a page under "
            "docs/guide/connecting-apps/ listed in index.md.\n"
            "See docs/guide/connecting-apps/elabftw.md for the template."
        )
        return 1

    print(f"Connected Apps docs check passed ({len(codes)} integrations documented).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
