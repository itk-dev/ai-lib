# Architecture Decision Records

This directory contains Architecture Decision Records (ADRs) for the
**ai-lib** project. An ADR captures a single significant architectural
decision, the context around it, and its consequences, so the reasoning
remains discoverable long after the decision has been made.

See [adr.github.io](https://adr.github.io/) for background on the format.

## Conventions

- ADRs live in `docs/adr/` and are named `NNN-brief-title.md`.
- Numbers are three-digit and sequential, starting at `001`.
- New ADRs are created via the `itkdev-adr` Claude skill, which
  provides the agreed template and status values.
- Status values: **Draft**, **Accepted**, **Rejected**,
  **Deprecated by NNN**, **Supersedes NNN**.
- Update this index whenever an ADR is added or its status changes.

## Index

| Number                                       | Title                                  | Status   | Date       |
| -------------------------------------------- | -------------------------------------- | -------- | ---------- |
| [001](001-tech-stack-docker-symfony.md)      | Tech stack: Docker + Symfony           | Accepted | 2026-06-08 |
