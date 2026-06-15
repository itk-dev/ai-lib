# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial Symfony 8 application scaffold on the ITK Dev Docker
  `symfony-8` template (phpfpm 8.4, nginx, MariaDB, Mailpit, Traefik),
  including dev dependencies for coding standards (`php-cs-fixer`,
  `twig-cs-fixer`) and composer normalization
  ([#1](https://github.com/itk-dev/ai-lib/issues/1)).
- Architecture Decision Records under `docs/adr/` with index and the
  first ADR `001-tech-stack-docker-symfony`
  ([#11](https://github.com/itk-dev/ai-lib/issues/11)).
- `CLAUDE.md` with project-level operating instructions for AI agents
  — stack, structure, execution policy, branching, commits, CHANGELOG,
  ADR conventions, translations, brand env vars, Tailwind rebuild
  notes, and the domain glossary
  ([#5](https://github.com/itk-dev/ai-lib/issues/5)).
- Human-facing `README.md` rewritten around the AI Bibliotek catalog
  — project description, status banner, feature list, tech stack,
  Task-based local development workflow, contributing pointers, and
  prototype references
  ([#28](https://github.com/itk-dev/ai-lib/issues/28)).
- `CONTRIBUTING.md` documenting branching, Conventional Commits,
  coding standards, changelog expectations, and the pull-request
  workflow
  ([#9](https://github.com/itk-dev/ai-lib/issues/9)).
- Project license declared as **MPL-2.0** — full `LICENSE` text at
  the repo root, `composer.json` `license` field updated from
  `proprietary` to `MPL-2.0`, and ADR `002-project-license-mpl-2`
  recording the rationale
  ([#32](https://github.com/itk-dev/ai-lib/issues/32)).
- `Taskfile.yml` exposing common developer commands via `task --list`
  (compose helpers, composer, console, coding-standards family) with
  README updates documenting `task` as a host requirement
  ([#29](https://github.com/itk-dev/ai-lib/issues/29)).
- Frontend tooling: Tailwind CSS via `symfonycasts/tailwind-bundle`,
  Symfony AssetMapper, and Stimulus via `symfony/stimulus-bundle`,
  with base Twig layout (`templates/base.html.twig`), asset
  entrypoints (`assets/app.js`, `assets/styles/app.css`), Tailwind v4
  design tokens (`@theme`), and ADR `002-frontend-tooling`
  ([#38](https://github.com/itk-dev/ai-lib/issues/38)).
- PHPUnit test harness with a 100 % coverage gate enforced in CI via
  `rregeer/phpunit-coverage-check`
  ([#31](https://github.com/itk-dev/ai-lib/issues/31)).
- Placeholder frontpage at `/` (`App\Controller\FrontpageController`)
  previewing the AI Bibliotek design with hardcoded sample data
  (hero, search box, sample-assistant rail, "Sådan virker det" steps),
  site chrome (header with brand + nav, footer), the Stimulus
  `nav_toggle_controller` driving the mobile menu, and a
  `block-on-label` GitHub Action providing a per-PR merge gate
  ([#40](https://github.com/itk-dev/ai-lib/issues/40)).
- User authentication: `User` Doctrine entity (email, hashed password,
  roles), `UserRepository` (with `PasswordUpgraderInterface`), the
  `UserManager` service that hides persistence + hashing, form-login
  firewall + `/login` + `/logout`, fixtures for two baseline users
  (`alice@example.test`, `bob@example.test` — password `password`),
  console commands `app:user:create` and `app:user:change-password`,
  and end-to-end functional + unit tests
  ([#2](https://github.com/itk-dev/ai-lib/issues/2)).
- Site chrome (header with brand + nav, footer) in
  `templates/base.html.twig`, with the Fraunces/Geist font stack
  preloaded from Google Fonts.
- Tailwind v4 design tokens (`@theme` in `assets/styles/app.css`)
  matching the prototype palette and typography.
- Stimulus controller `nav_toggle_controller` driving the mobile
  navigation menu.
- GitHub Action `block-on-label` that fails the check while a
  `do-not-merge` label is applied to a pull request, providing a
  per-PR merge gate for dependencies (e.g. another PR that must land
  first).
- `LICENSE` file at repo root containing the full Mozilla Public License 2.0 text.
- Project license declared as **MPL-2.0** (Mozilla Public License 2.0); the
  `license` field in `composer.json` updated from the Symfony skeleton
  default `proprietary` to the SPDX identifier `MPL-2.0`.
- `Taskfile.yml` exposing common developer commands via `task --list`
  (compose helpers, composer, console, coding-standards family).
- README documents [Task](https://taskfile.dev) as a host requirement and
  uses `task` targets in the *Common commands* section.
- `docs/adr/` with index (`docs/adr/README.md`) and ADR `001-tech-stack-docker-symfony`
  documenting the choice of Symfony 8 on the ITK Dev Docker `symfony-8` template.
- `CONTRIBUTING.md` documenting branching, Conventional Commits, coding
  standards, changelog expectations and the pull-request workflow.
