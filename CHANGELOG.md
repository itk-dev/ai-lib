# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Three reusable page layout components under
  `templates/components/Layout/`. Site header, `<main>`, and
  footer now share the wide container (`max-w-wide`, ≈ 1600px
  from the mocks' `--container-wide`) so all three chrome edges
  align — matching the prototype's `index.html`, which puts
  `container-wide` on all three. `<twig:Layout:SingleColumn>`
  renders a stacked flow inside that wide `<main>` (no extra
  horizontal constraint of its own — inner components like
  `<twig:Hero>` or `<twig:Box>` carry whatever narrower
  max-width they need) and is adopted on the frontpage as the
  reference consumer. `<twig:Layout:ThreeColumn>` (slots `start`,
  `main`, `end`) and `<twig:Layout:ContentWithAsides>` (slots
  `main`, `meta`, `actions` with sticky asides) cover the
  catalogue and detail surfaces. The previous ad-hoc
  `max-w-[1600px]` literals on the header, footer, and `<main>`
  are replaced by the token-backed `max-w-wide` utility. The
  narrow `--container-narrow` token (≈ 1180px) is defined in
  `@theme` for future use but no current page applies it,
  mirroring the prototype where `.container` is defined but
  unused. Grid CSS lives in `assets/styles/app.css` under
  `@layer components`. Three pages adopt the new layouts as
  reference consumers: the frontpage (`SingleColumn`),
  `/assistant/new` (`SingleColumn`), `/search` (`ThreeColumn`
  with filters in the `start` slot and an empty `end` slot
  reserved for a future context rail), and `/assistant/{id}`
  (`ContentWithAsides` with the title + description in `main`,
  runtime details in `meta`, and tags in `actions`)
  ([#144](https://github.com/itk-dev/ai-reolen/issues/144)).
- Branded HTTP 401 and 403 pages for the firewall entry
  points. `App\Security\UnauthorizedEntryPoint` now renders
  `templates/security/unauthorized.html.twig` ("Log ind
  påkrævet" + login / register CTA) instead of the empty
  401 that made Firefox fall back to its built-in error UI.
  `App\Security\AccessDeniedHandler` (wired on the `main`
  firewall as `access_denied_handler`) renders
  `templates/security/access_denied.html.twig` ("Adgang
  nægtet" + link back to the frontpage) instead of the
  default blank Symfony 403. Both templates extend the
  public base layout so brand chrome stays present, and the
  two cases (no authentication vs. wrong role) intentionally
  render differently
  ([Symfony docs](https://symfony.com/doc/current/security/access_denied_handler.html)).
- Catalogue result ordering. The `/search` page gains a "Sortering"
  box in the filter column, above the filters, offering newest, oldest,
  recently-updated, and name (A–Å / Å–A) orderings, defaulting to
  newest-first. The chosen ordering travels in the `?sort=` URL
  parameter, combines with the active search and facet filters (each
  form mirrors the other's state as hidden inputs, so changing the sort
  preserves the filters and toggling a facet preserves the sort), and
  survives pagination. Changing the order auto-submits (Stimulus, with a
  no-JS fallback) and returns to page 1
  ([#19](https://github.com/itk-dev/ai-reolen/issues/19)).
- Transactional registration emails. After a successful
  `/register` submission, two emails are dispatched: an admin
  notification to the moderator inbox (the existing
  `admin_recipient` setting) and a "thanks, awaiting approval"
  confirmation to the user. Both messages render from
  admin-editable Markdown templates persisted on the
  `Setting` entity (`admin_notification_subject` /
  `_body`, `registration_confirmation_subject` / `_body`),
  with `%token%` placeholders resolved by a new
  `App\Mail\EmailTemplateRenderer`. The `/admin/settings/email`
  page grows two new fieldsets to edit subject + body per
  email, with the available placeholders listed inline.
  Transport failures are logged and swallowed so a mailer
  outage doesn't undo a successful registration. Partially
  closes #119 — the one-time login link lands in a follow-up
  ([#119](https://github.com/itk-dev/ai-reolen/issues/119)).
- Self-service profile edit page at `/profile/edit`. Any
  authenticated user — regardless of role — can update their
  own display name. The subject is always read off the
  security context, never a path parameter, so the route
  can't be used to address another user's row. The
  controller stays thin: hands the submission to a new
  `App\Form\ProfileType` and persists through the existing
  `UserManager::updateUser($email, name: ...)` path so
  password / role / status mutations stay in one place. The
  `UserMenu` "Redigér profil" item lights up automatically
  now that the route exists
  ([#128](https://github.com/itk-dev/ai-reolen/issues/128)).
- Catalogue free-text search and tag filtering. The `/search`
  page gains a search box that matches the assistant title and
  description (case-insensitive) and a "Tags" facet alongside
  the existing Sprogmodel and Rammeværk facets; all combine with
  each other and free-text search, and every active filter is
  reflected in the URL and as a removable chip. Ticking a facet
  checkbox submits the filter form immediately (Stimulus, with the
  Apply button as the no-JS fallback). The frontpage search box now
  submits to the catalogue, so a query from the homepage lands on
  `/search` with results. Tags are now a
  relational `Tag` entity joined to `Assistant` many-to-many
  (replacing the previous JSON column), recorded in
  [ADR 008](docs/adr/008-tags-as-relational-entity.md)
  ([#18](https://github.com/itk-dev/ai-reolen/issues/18)).
- Anti-flood protection on both anonymous form surfaces. The
  registration form gets a per-IP rate limiter (env-tunable
  via `REGISTRATION_RATE_LIMIT_PER_IP`, defaults to 10/day) and
  a system-wide ceiling (`REGISTRATION_RATE_LIMIT_SYSTEM`,
  defaults to 100/day), both wired through Symfony's Rate
  Limiter component and backed by a dedicated
  `cache.rate_limiter` pool. Either limiter rejecting the
  request renders HTTP 429 with a localised
  `register.error.rate_limited` message. The login form gets
  Symfony Security's built-in `login_throttling` on the `main`
  firewall (5 attempts / 15 minutes per `<IP, username>`),
  which surfaces the bundled Danish "for mange mislykkede
  loginforsøg" message on the login page. Registration is
  also now idempotent on a duplicate e-mail — the response
  matches a fresh signup so a probe can't learn whether the
  address is taken
  ([#107](https://github.com/itk-dev/ai-reolen/issues/107)).
- `UserFixtures` now seeds five extra accounts on top of
  `alice@example.test` / `bob@example.test` so every
  `App\Security\Roles` value and every `App\Enum\UserStatus`
  case is represented out of the box: `admin@aarhus.dk`
  (`ROLE_ADMIN`, `Approved`), `manager@aarhus.dk`
  (`ROLE_DOMAIN_MANAGER`, `Approved`),
  `pending@aalborg.dk` (`Pending`), `awaiting@aalborg.dk`
  (`AwaitingEmailConfirmation`), and `blocked@odense.dk`
  (`Blocked`). All share the plain `password` for
  paste-friendly local login. Each address is exposed as a
  public constant on `UserFixtures` so downstream lookups
  don't repeat the string
  ([#126](https://github.com/itk-dev/ai-reolen/issues/126)).
- Admin-page roundup: `/admin/users` gains a primary
  **"Opret bruger"** button (gated on `ROLE_ADMIN`) and a full
  HTML create form at `/admin/users/new` backed by a non-mapped
  `UserCreateType` that delegates to
  `UserManager::createUser()` for hashing + persistence. Both
  `/admin/users` and `/admin/organization` lists are migrated
  from ad-hoc `<ul>` cards to the shared `<twig:Table>`
  component family. `/admin/settings` is split into
  `/admin/settings/site` (brand name / tagline / initials) and
  `/admin/settings/email` (admin notification recipient); the
  bare `/admin/settings` route redirects to the site page, and
  a new `<twig:Tabs>` component (plus the
  `admin/settings/_tabs.html.twig` partial) renders the
  switcher between the two surfaces with the active tab
  marked `aria-current="page"`. Brand identity now resolves
  through `SettingsManager::getBrandName()` / tagline /
  initials with `BRAND_*` env vars as the fallback default; an
  `App\Twig\BrandExtension` exposes the resolved values as the
  `brand_name` / `brand_tagline` / `brand_initials` Twig
  globals. Every `/admin/**` page renders against a light
  pink (`bg-admin-bg`) body background so operators see at a
  glance that they're in the back office
  ([#124](https://github.com/itk-dev/ai-reolen/issues/124)).
- The `/assistant/new` upload widget now pretty-prints the
  JSON it drops into the editable textarea (two-space indent,
  preserved newlines) so the operator can read and edit the
  config before submit. The server-side path is unchanged —
  Doctrine's `JSON` column type decodes the form value to an
  array and re-encodes it without whitespace, so whatever
  indentation the user typed (file upload, paste, hand-edit)
  collapses to minified JSON on disk
  ([#101](https://github.com/itk-dev/ai-reolen/issues/14)).
- `Assistant.openwebui_config` JSON column for storing the
  uploaded OpenWebUI export verbatim, plus a create form at
  `/assistant/new` with a file-upload field that AJAX-validates
  the JSON before submit and writes the result into an editable
  textarea (users can also paste/type JSON directly). A shared
  `App\Validator\OpenWebUiConfigValidator` service hosts the
  validation pipeline (JSON syntax + a temporary slow-validation
  scaffold for UI testing); the uploaded file itself is never
  persisted, only the parsed JSON reaches the database. The
  create form picks up the project's default-deny gating: any
  authenticated user can submit, no admin role required
  ([#14](https://github.com/itk-dev/ai-lib/issues/14)).
- Higher-contrast zebra striping on the shared `<twig:Table>`
  component. Even rows now use a new dedicated
  `--color-row-alt` (`#f1f3f5`) design token instead of the
  near-white `--color-surface-2`, so admin lists read as
  alternating light-gray / white rather than a wall of white.
  Odd rows are made explicit `bg-bg` so the stripes survive
  tinted backgrounds. `--color-surface-2` is left alone — it
  remains the project's hover-state tone
  ([#130](https://github.com/itk-dev/ai-reolen/issues/130)).
- `app:user:update` console command that updates an existing
  user's display name, roles, and / or lifecycle status. Each
  field is optional — omitting it leaves the value untouched.
  `--role` may be repeated to set multiple roles and replaces
  the current role list wholesale; unknown role identifiers
  and unknown status strings are rejected with a clear message.
  Sits behind a new `UserManager::updateUser()` service method
  ([#122](https://github.com/itk-dev/ai-reolen/issues/122)).
- `task site-install` learned an opt-in `RESET=1` parameter
  (`RESET=1 task site-install`) that drops and recreates the
  application database before running migrations, so a fresh
  install on top of an existing schema doesn't fail with "table
  already exists". The default invocation stays non-destructive.
- Foundation for outbound transactional mail: `symfony/mailer`
  installed and wired to the existing `mail` container (Mailpit) via
  `MAILER_DSN=smtp://mail:1025` in `.env`, with a deploy-overridable
  `MAILER_FROM` sender. `.env.test` pins `null://null` so the test
  suite never hits a real transport. Generic `setting` table (`name`
  unique, `value` nullable text) backs an `App\Settings\SettingsManager`
  service exposing a typed `getAdminRecipient()` / `setAdminRecipient()`
  pair. A new admin-only page at `/admin/settings` (`ROLE_ADMIN`, CSRF
  on POST, 422 on invalid email) lets administrators set the recipient
  for registration-related notifications; the user-menu surfaces it
  under **Administration → Indstillinger** when the acting user is a
  site admin. Mail templates and the actual signup/admin-notification
  /one-time-login flows land in follow-up work
  ([#119](https://github.com/itk-dev/ai-lib/issues/119)).
- Data fixtures now assign a creating user (round-robin
  `alice@example.test` / `bob@example.test`) to each seeded assistant and
  organization, so the created-by/modified-by blame relation is exercised by
  local-development data.
- Integrated [`itk-dev/entity-bundle`](https://github.com/itk-dev/entity-bundle)
  as the shared entity foundation. New `App\Entity\AbstractEntity` extends the
  bundle's `AbstractITKDevEntity`, giving every domain entity a ULID primary
  key plus timestamps, created-by/modified-by blame, archivability, and
  anonymization status. `User`, `Assistant`, and `Organization` now extend it
  (integer ids replaced by ULIDs), are marked `#[Auditable]`, and `User`'s PII
  is annotated with `#[Anonymize]`. All bundle features are enabled except soft
  delete (`config/packages/itk_dev_entity.yaml`); `damienharper/auditor-bundle`
  is wired for the audit log. Admin route `{id}` requirements (`User`
  approve/block, `Organization` edit/delete) accept `Requirement::ULID`
  instead of `\d+`. Records the decision in
  [ADR 007](docs/adr/007-entity-foundation-entity-bundle.md)
  ([#104](https://github.com/itk-dev/ai-lib/issues/104)).
- Admin CRUD for `Organization` at `/admin/organization` (list,
  create, edit, delete) per ADR 003. Built with `symfony/form`
  (newly added dependency) + raw Twig templates, multi-value
  email-domain field via a textarea with a `CallbackTransformer`,
  and Danish UI copy under `admin.organization.*`. Auth gating
  intentionally deferred to a follow-up issue
  ([#76](https://github.com/itk-dev/ai-reolen/issues/76)).
- Default-deny `access_control` rule on the `main` firewall: every
  route now requires `IS_AUTHENTICATED_FULLY` except the
  `PUBLIC_ACCESS` allow-list (`/login`, `/logout`, `/register`,
  `/register/pending`). Anonymous visitors hitting a gated route
  get the standard Symfony redirect to `/login`, with the
  originally requested URL preserved so they land back on the
  page after signing in
  ([#97](https://github.com/itk-dev/ai-reolen/issues/97)).
- Authenticated-user dropdown menu in the top nav. The user's
  display name is the trigger; the menu groups into a **Bruger**
  section (Edit profile, Log out) and a role-gated
  **Administration** section (Administrér organisationer,
  Administrér brugere). Each item is only rendered when both the
  acting user holds the gating role and the target route is
  registered, so the menu degrades gracefully on branches where
  later admin PRs haven't landed yet. ARIA "Menu Button" pattern
  via a new Stimulus controller — Escape and outside-click close
  the menu
  ([#108](https://github.com/itk-dev/ai-reolen/issues/108)).
- Shared admin-list Table Twig component family
  (`templates/components/Table.html.twig` +
  `templates/components/Table/Head|Body|Row|HeadCell|Cell.html.twig`)
  that renders a semantic `<table>` inside a horizontal-scroll
  wrapper, with zebra-striped body rows and an `align` prop on
  the cell components for right-flushed action columns. First
  consumer migrations land alongside their respective PRs
  ([#112](https://github.com/itk-dev/ai-reolen/issues/112)).
- Anonymous self-signup at `/register`. The route is
  open to unauthenticated visitors; submissions go through
  `App\Security\Registration` which validates the email format,
  checks the right-hand-side domain against an env-backed allow-list
  (`REGISTRATION_ALLOWED_EMAIL_DOMAINS`, comma-separated; default
  empty so production must opt-in by setting the var, with
  `example.test` set in `.env.test` for the test suite), requires
  matching password confirmation, and creates the `User` with
  `status = Pending`. The user is redirected to `/register/pending`
  ("thanks, awaiting approval") and cannot sign in until a domain
  manager approves them. CSRF-protected via Symfony's
  `csrf_token('register')` helper. Localised in the existing
  `messages` domain
  ([#62](https://github.com/itk-dev/ai-reolen/issues/62)).
- Admin user-management surface at `/admin/users` per ADR 006. Lists
  users scoped by role — `ROLE_ADMIN` sees every user, a
  `ROLE_DOMAIN_MANAGER` sees only users whose email domain matches
  their own. Optional `?status=pending|approved|blocked` filter for
  the approval queue (`/admin/users/pending` redirects to
  `?status=pending`). Per-row Approve / Block buttons are gated by
  the `ManageUserVoter` from #84 (same-domain check) and the new
  `App\Security\UserApproval` service flips the status. The list
  view uses a new repository finder
  `UserRepository::findVisibleTo()`, scoped against the actor's
  role + email domain via the `EmailDomain` helper. CSRF-protected;
  `back` parameter on the action forms only honours
  `/admin/users…` URLs
  ([#64](https://github.com/itk-dev/ai-reolen/issues/64),
  [#85](https://github.com/itk-dev/ai-reolen/issues/85)).
- `ROLE_DOMAIN_MANAGER` + `ROLE_ADMIN` role identifiers
  (`App\Security\Roles`), `role_hierarchy` wiring in `security.yaml`
  so `ROLE_ADMIN` implies `ROLE_DOMAIN_MANAGER`, and a
  domain-scoped `ManageUserVoter` that grants the `MANAGE_USER` /
  `APPROVE_USER` / `BLOCK_USER` attributes when the acting user is a
  domain manager in the subject's email domain (or a site-wide
  admin). Lays the authorisation foundation for the admin approval
  queue (#64) and the scoped user-management list view (#85) per
  ADR 006
  ([#84](https://github.com/itk-dev/ai-reolen/issues/84)).
- `User.name` (display name) and `User.status` (lifecycle enum:
  `pending | approved | blocked`) per ADR 006, plus the
  `App\Enum\UserStatus` PHP enum. `UserManager::createUser()` now
  requires `name` and accepts an optional `status` (default
  `Approved` for the console / fixture path; the registration flow
  in #62 will pass `Pending`). The `app:user:create` console command
  takes a third `name` argument; fixtures seed Alice + Bob with
  display names. Schema is added via a single migration that
  backfills any existing rows with `name = ''` and
  `status = 'approved'`
  ([#45](https://github.com/itk-dev/ai-reolen/issues/45),
  [#83](https://github.com/itk-dev/ai-reolen/issues/83)).
- Added tailwind build to site-install task.
- Shared `Heading` Twig component (`templates/components/Heading.html.twig`)
  that renders `<h1>`–`<h6>` with size tokens centralised in one place.
  Refactors `assistant/show.html.twig`, `security/login.html.twig`,
  and the `PageHeader`, `Hero`, `EmptyState`, `Filter/Rail`
  components to use it
  ([#92](https://github.com/itk-dev/ai-reolen/issues/92)).
- `App\Security\AccountStatusChecker` implementing
  `UserCheckerInterface` — gates the login flow so any `User` whose
  `status` is not `Approved` is rejected before the password is
  verified, with distinct localised messages per state
  (`account.awaiting_email_confirmation`, `account.pending`,
  `account.blocked`) rendered in the `security` translation domain.
  Wired on the `main` firewall via `security.yaml`'s `user_checker:`
  key
  ([#63](https://github.com/itk-dev/ai-reolen/issues/63),
  [#103](https://github.com/itk-dev/ai-reolen/issues/103)).
- Shared `DescriptionList` Twig component family
  (`templates/components/DescriptionList/List.html.twig` +
  `templates/components/DescriptionList/Item.html.twig`) for
  label/value pairs. The assistant detail's runtime attribute grid
  adopts it
  ([#94](https://github.com/itk-dev/ai-reolen/issues/94)).
- ADR `005-organization-entity` recording the decision to introduce
  `Organization` as a first-class entity with name, multiple emails,
  and a default framework — no language-model field, with the
  `User → Organization` reference and admin CRUD tracked as separate
  issues
  ([#65](https://github.com/itk-dev/ai-reolen/issues/65)).
- `Organization` Doctrine entity (name, list of email domains,
  default framework), repository, migration, and
  `OrganizationFixtures` seeding three baseline kommuner (Aarhus,
  Aalborg, Odense). First step of ADR 005 — `User → Organization`,
  CRUD, and assistant autocomplete land in follow-up issues
  ([#75](https://github.com/itk-dev/ai-reolen/issues/75)).
- `User.name` (display name) and `User.status` (`UserStatus` enum:
  `awaiting_email_confirmation | pending | approved | blocked`)
  fields
  ([#45](https://github.com/itk-dev/ai-reolen/issues/45),
  [#83](https://github.com/itk-dev/ai-reolen/issues/83),
  [#103](https://github.com/itk-dev/ai-reolen/issues/103)).
- `ROLE_DOMAIN_MANAGER` + `ROLE_ADMIN` role identifiers
  (`App\Security\Roles`), `role_hierarchy` wiring in `security.yaml`
  so `ROLE_ADMIN` implies `ROLE_DOMAIN_MANAGER`, and a
  domain-scoped `ManageUserVoter` that grants the `MANAGE_USER` /
  `APPROVE_USER` / `BLOCK_USER` attributes when the acting user is a
  domain manager in the subject's email domain (or a site-wide
  admin).
  ([#84](https://github.com/itk-dev/ai-reolen/issues/84)).
- Test-env `framework.exceptions` override so
  `NotFoundHttpException` logs at `info` instead of `error`, keeping
  PHPUnit output clean when a test deliberately asserts a 404
  ([#95](https://github.com/itk-dev/ai-reolen/issues/95)).
- Shared `Alert` Twig component (`templates/components/Alert.html.twig`)
  for flash messages and inline errors. `type` (`success` | `error` |
  `warning` | `info`) drives the ARIA role; the login error block
  adopts it
  ([#93](https://github.com/itk-dev/ai-reolen/issues/93)).
- Catalogue listing page with filters
  ([#15](https://github.com/itk-dev/ai-reolen/issues/15)).
- Initial Symfony 8 application scaffold on the ITK Dev Docker
  `symfony-8` template (phpfpm 8.4, nginx, MariaDB, Mailpit, Traefik),
  including dev dependencies for coding standards (`php-cs-fixer`,
  `twig-cs-fixer`) and composer normalization
  ([#1](https://github.com/itk-dev/ai-reolen/issues/1)).
- Architecture Decision Records under `docs/adr/` with index and the
  first ADR `001-tech-stack-docker-symfony`
  ([#11](https://github.com/itk-dev/ai-reolen/issues/11)).
- `CLAUDE.md` with project-level operating instructions for AI agents
  — stack, structure, execution policy, branching, commits, CHANGELOG,
  ADR conventions, translations, brand env vars, Tailwind rebuild
  notes, and the domain glossary
  ([#5](https://github.com/itk-dev/ai-reolen/issues/5)).
- Human-facing `README.md` rewritten around the AI Bibliotek catalog
  — project description, status banner, feature list, tech stack,
  Task-based local development workflow, contributing pointers, and
  prototype references
  ([#28](https://github.com/itk-dev/ai-reolen/issues/28)).
- `CONTRIBUTING.md` documenting branching, Conventional Commits,
  coding standards, changelog expectations, and the pull-request
  workflow
  ([#9](https://github.com/itk-dev/ai-reolen/issues/9)).
- Project license declared as **MPL-2.0** — full `LICENSE` text at
  the repo root, `composer.json` `license` field updated from
  `proprietary` to `MPL-2.0`, and ADR `004-project-license-mpl-2`
  recording the rationale
  ([#32](https://github.com/itk-dev/ai-reolen/issues/32)).
- `Taskfile.yml` exposing common developer commands via `task --list`
  (compose helpers, composer, console, coding-standards family) with
  README updates documenting `task` as a host requirement
  ([#29](https://github.com/itk-dev/ai-reolen/issues/29)).
- Frontend tooling: Tailwind CSS via `symfonycasts/tailwind-bundle`,
  Symfony AssetMapper, and Stimulus via `symfony/stimulus-bundle`,
  with base Twig layout (`templates/base.html.twig`), asset
  entrypoints (`assets/app.js`, `assets/styles/app.css`), Tailwind v4
  design tokens (`@theme`), and ADR `002-frontend-tooling`
  ([#38](https://github.com/itk-dev/ai-reolen/issues/38)).
- PHPUnit test harness with a 100 % coverage gate enforced in CI via
  `rregeer/phpunit-coverage-check`
  ([#31](https://github.com/itk-dev/ai-reolen/issues/31)).
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
  ([#14](https://github.com/itk-dev/ai-reolen/issues/14), [#16](https://github.com/itk-dev/ai-reolen/issues/16)).
- Base Twig layout (`templates/base.html.twig`) and frontend asset
  entrypoints (`assets/app.js`, `assets/styles/app.css`).
- Placeholder frontpage at `/` (`App\Controller\FrontpageController`)
  previewing the AI Bibliotek design with hardcoded sample data
  (hero, search box, sample-assistant rail, "Sådan virker det" steps),
  site chrome (header with brand + nav, footer), the Stimulus
  `nav_toggle_controller` driving the mobile menu, and a
  `block-on-label` GitHub Action providing a per-PR merge gate
  ([#40](https://github.com/itk-dev/ai-reolen/issues/40)).
- User authentication: `User` Doctrine entity (email, hashed password,
  roles), `UserRepository` (with `PasswordUpgraderInterface`), the
  `UserManager` service that hides persistence + hashing, form-login
  firewall + `/login` + `/logout`, fixtures for two baseline users
  (`alice@example.test`, `bob@example.test` — password `password`),
  console commands `app:user:create` and `app:user:change-password`,
  and end-to-end functional + unit tests
  ([#2](https://github.com/itk-dev/ai-reolen/issues/2)).
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
  ([#69](https://github.com/itk-dev/ai-reolen/issues/69)).
