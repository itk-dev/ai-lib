# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `CLAUDE.md` with project-level operating instructions for AI agents
  (stack, structure, execution policy, branching, commits, CHANGELOG,
  ADRs, domain glossary).
- Initial Symfony 8 application scaffold.
- PHPUnit test harness with 100% coverage gate enforced in CI via
  `rregeer/phpunit-coverage-check`
  ([#31](https://github.com/itk-dev/ai-lib/issues/31)).

### Changed

- README refocused as human-facing project documentation: project purpose,
  tech stack, and local development bootstrap. Developer command reference
  moved to `CLAUDE.md` (and later `CONTRIBUTING.md`, tracked in #9).
- ITK Dev Docker setup via the `symfony-8` template (phpfpm 8.4, nginx, MariaDB, Mailpit).
- Dev dependencies for coding standards and composer normalization:
  `ergebnis/composer-normalize`, `friendsofphp/php-cs-fixer`, `vincentlanglet/twig-cs-fixer`.
- Project README with local development instructions.
- Frontend tooling: Tailwind CSS (via `symfonycasts/tailwind-bundle`),
  Symfony AssetMapper, and Stimulus (via `symfony/stimulus-bundle`).
  Decision recorded in [ADR 002](docs/adr/002-frontend-tooling.md).
- Base Twig layout (`templates/base.html.twig`) and frontend asset
  entrypoints (`assets/app.js`, `assets/styles/app.css`).
- Placeholder frontpage at `/` (`App\Controller\FrontpageController`)
  that previews the AI Bibliotek design with hardcoded sample data:
  hero, search prompt, sample-assistant rail, "Sådan virker det"
  steps, and "Kommer snart" chips. Follows the prototype mock at
  `itk-dev/research-projects/docs/public/projects/ai-bibliotek/mocks`.
- Site chrome (header with brand + nav, footer) in
  `templates/base.html.twig`, with the Fraunces/Geist font stack
  preloaded from Google Fonts.
- Tailwind v4 design tokens (`@theme` in `assets/styles/app.css`)
  matching the prototype palette and typography.
- Stimulus controller `nav_toggle_controller` driving the mobile
  navigation menu.
- Functional smoke test for the frontpage
  (`tests/Controller/FrontpageControllerTest.php`); will start running
  once PHPUnit lands (#31).
- GitHub Action `block-on-label` that fails the check while a
  `do-not-merge` label is applied to a pull request, providing a
  per-PR merge gate for dependencies (e.g. another PR that must land
  first).
- `LICENSE` file at repo root containing the full Mozilla Public License 2.0 text.
- ADR `docs/adr/002-project-license-mpl-2.md` recording the MPL-2.0 license
  decision and its rationale.
- License section in `README.md` referencing the new `LICENSE` file and ADR.
- Project license declared as **MPL-2.0** (Mozilla Public License 2.0); the
  `license` field in `composer.json` updated from the Symfony skeleton
  default `proprietary` to the SPDX identifier `MPL-2.0`.
- Added develop branch
- `Taskfile.yml` exposing common developer commands via `task --list`
  (compose helpers, composer, console, coding-standards family).
- README documents [Task](https://taskfile.dev) as a host requirement and
  uses `task` targets in the *Common commands* section.
- `docs/adr/` with index (`docs/adr/README.md`) and ADR `001-tech-stack-docker-symfony`
  documenting the choice of Symfony 8 on the ITK Dev Docker `symfony-8` template.
- `CONTRIBUTING.md` documenting branching, Conventional Commits, coding
  standards, changelog expectations and the pull-request workflow.
- Rewrite the project README around the AI Bibliotek catalog: adds project
  description, status banner, feature list, tech stack, Task-based local
  development workflow, contributing pointers, and prototype references.
