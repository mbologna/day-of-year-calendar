# AGENTS.md

Notes for coding agents working in this repository. [README.md](README.md) covers what it does and how to use it.

## Versioning

SemVer with changelog-only releases: `vX.Y.Z` tags, each with a GitHub Release listing the changes, per the [versioning standard](https://github.com/mbologna/.github/blob/main/VERSIONING.md).

- A tag builds and deploys nothing: `main` is what runs, deployed to GitHub Pages on every push.
- Never create, move or delete tags, and never edit a version number to cut a release: auto-release tags from the commit types (`feat` minor; `fix`, `perf`, `revert` patch; `!` or a `BREAKING CHANGE:` footer major).
- Write Conventional Commit subjects; they decide the next version.
- `version` in `pyproject.toml` is not kept in step with the tags, and nothing reads it: the tag is the version.
