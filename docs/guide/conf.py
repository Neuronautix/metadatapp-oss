# Sphinx configuration for the Metadatapp User & Integration Guide.
#
# Authored in Markdown via MyST-Parser so the repo agent team can enrich pages
# with the same tooling used everywhere else in this repository. Build locally
# with:
#
#     pip install -r docs/guide/requirements.txt
#     sphinx-build -W -b html docs/guide docs/guide/_build/html
#
# The `-W` flag turns warnings (including broken cross-references) into errors,
# matching the CI gate in .github/workflows/docs.yml.

project = "Metadatapp"
project_copyright = "2026, Neuronautix"
author = "Neuronautix"

# -- General configuration ---------------------------------------------------

extensions = [
    "myst_parser",
    "sphinx_design",  # grid/card directives used on the landing page
    "sphinxcontrib.mermaid",  # {mermaid} diagrams in concepts/architecture pages
]

# Markdown is the only source format; keep authoring friction (and the barrier
# for AI contributors) as low as possible.
source_suffix = {
    ".md": "markdown",
}

# MyST extensions used across the guide. Keep this list intentional: every entry
# is something the pages actually rely on.
myst_enable_extensions = [
    "colon_fence",   # ::: fenced admonitions
    "deflist",       # definition lists for credential field tables
    "linkify",       # bare URLs become links
    "substitution",  # reusable values (see myst_substitutions)
]

myst_heading_anchors = 3

# Reusable substitutions so canonical values live in exactly one place.
myst_substitutions = {
    "local_api": "https://metadatapp.test",
    "local_osoma": "https://osoma.metadatapp.test",
}

templates_path = ["_templates"]
exclude_patterns = ["_build", "Thumbs.db", ".DS_Store"]

# -- HTML output -------------------------------------------------------------

html_theme = "furo"
html_title = "Metadatapp User & Integration Guide"
html_static_path = ["_static"]

# Don't fail the build if _static is empty (it is, for now).
html_css_files = []

# -- OpenAPI spec for the API reference --------------------------------------
# Single source of truth: the committed spec at osoma/resources/metadatapp.json.
# Copy it into _static at build time (works locally, in CI, and on Read the Docs)
# so the API reference page can render it with Redoc. The copy is gitignored.
import os
import shutil

_here = os.path.dirname(__file__)
_spec_src = os.path.join(_here, "..", "..", "osoma", "resources", "metadatapp.json")
_spec_dst_dir = os.path.join(_here, "_static", "openapi")
if os.path.isfile(_spec_src):
    os.makedirs(_spec_dst_dir, exist_ok=True)
    shutil.copyfile(_spec_src, os.path.join(_spec_dst_dir, "metadatapp.json"))

