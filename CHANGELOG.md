# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Shared `Heading` Twig component (`templates/components/Heading.html.twig`)
  that renders `<h1>`–`<h6>` with size tokens centralised in one place.
  Refactors `assistant/show.html.twig`, `security/login.html.twig`,
  and the `PageHeader`, `Hero`, `EmptyState`, `Filter/Rail`
  components to use it
  ([#92](https://github.com/itk-dev/ai-lib/issues/92)).
- `App\Security\AccountStatusChecker` implementing
  `UserCheckerInterface` — gates the login flow so any `User` whose
  `status` is not `Approved` is rejected before the password is
  verified, with distinct localised messages per state
  (`account.awaiting_email_confirmation`, `account.pending`,
  `account.blocked`) rendered in the `security` translation domain.
  Wired on the `main` firewall via `security.yaml`'s `user_checker:`
  key
  ([#63](https://github.com/itk-dev/ai-lib/issues/63),
  [#103](https://github.com/itk-dev/ai-lib/issues/103)).
- Shared `DescriptionList` Twig component family
  (`templates/components/DescriptionList/List.html.twig` +
  `templates/components/DescriptionList/Item.html.twig`) for
  label/value pairs. The assistant detail's runtime attribute grid
  adopts it
  ([#94](https://github.com/itk-dev/ai-lib/issues/94)).
- ADR `005-organization-entity` recording the decision to introduce
  `Organization` as a first-class entity with name, multiple emails,
  and a default framework — no language-model field, with the
  `User → Organization` reference and admin CRUD tracked as separate
  issues
  ([#65](https://github.com/itk-dev/ai-lib/issues/65)).
- `Organization` Doctrine entity (name, list of email domains,
  default framework), repository, migration, and
  `OrganizationFixtures` seeding three baseline kommuner (Aarhus,
  Aalborg, Odense). First step of ADR 005 — `User → Organization`,
  CRUD, and assistant autocomplete land in follow-up issues
  ([#75](https://github.com/itk-dev/ai-lib/issues/75)).
- `User.name` (display name) and `User.status` (`UserStatus` enum:
  `awaiting_email_confirmation | pending | approved | blocked`)
  fields
  ([#45](https://github.com/itk-dev/ai-lib/issues/45),
  [#83](https://github.com/itk-dev/ai-lib/issues/83),
  [#103](https://github.com/itk-dev/ai-lib/issues/103)).
- `ROLE_DOMAIN_MANAGER` + `ROLE_ADMIN` role identifiers
  (`App\Security\Roles`), `role_hierarchy` wiring in `security.yaml`
  so `ROLE_ADMIN` implies `ROLE_DOMAIN_MANAGER`, and a
  domain-scoped `ManageUserVoter` that grants the `MANAGE_USER` /
  `APPROVE_USER` / `BLOCK_USER` attributes when the acting user is a
  domain manager in the subject's email domain (or a site-wide
  admin).
  ([#84](https://github.com/itk-dev/ai-lib/issues/84)).
- Test-env `framework.exceptions` override so
  `NotFoundHttpException` logs at `info` instead of `error`, keeping
  PHPUnit output clean when a test deliberately asserts a 404
  ([#95](https://github.com/itk-dev/ai-lib/issues/95)).
- Shared `Alert` Twig component (`templates/components/Alert.html.twig`)
  for flash messages and inline errors. `type` (`success` | `error` |
  `warning` | `info`) drives the ARIA role; the login error block
  adopts it
  ([#93](https://github.com/itk-dev/ai-lib/issues/93)).
- Catalogue listing page with filters
  ([#15](https://github.com/itk-dev/ai-lib/issues/15)).
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
  `proprietary` to `MPL-2.0`, and ADR `004-project-license-mpl-2`
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
  ([#14](https://github.com/itk-dev/ai-lib/issues/14), [#16](https://github.com/itk-dev/ai-lib/issues/16)).
- Base Twig layout (`templates/base.html.twig`) and frontend asset
  entrypoints (`assets/app.js`, `assets/styles/app.css`).
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
- PHPUnit suite split into `unit` (no database) and `integration` (full
  kernel) testsuites under `tests/Unit/` and `tests/Integration/`, with
  transactional database isolation per integration test via
  `dama/doctrine-test-bundle`. The integration suite uses a dedicated
  `tests/bootstrap_integration.php` that builds the schema from ORM
  metadata and loads baseline `UserFixtures` once before any test;
  DAMA's per-test transaction rolls back mutations so the baseline
  persists. The default `tests/bootstrap.php` is minimal and is used
  by `task test-unit`. `task test-unit` and `task test-integration`
  expose the suites individually.
- Reusable Twig form components under `templates/components/Form/`:
  `Form/Label`, `Form/Input`, and `Form/Button` (with `variant` and
  `size` props for future styling variants). The `/login` template
  consumes them instead of inlining the input/label/button markup.
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
- GitHub issue template `.github/ISSUE_TEMPLATE/issue.md` and pull-request
  template `.github/PULL_REQUEST_TEMPLATE.md`, each with a human-facing
  "Resume" / checklist section followed by an "AI specificities" detail
  block so other agents can continue work from a structured brief
  ([#69](https://github.com/itk-dev/ai-lib/issues/69)).
