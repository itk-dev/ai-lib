# AI Bibliotek (ai-lib)

A shared catalog of AI assistants for the Danish public sector. `ai-lib`
lets contributors export, share, search, and import assistants — using the
OpenWebUI JSON format as the interchange — and provides moderation and
metadata around what ends up in the catalog.

## Tech stack

- [Docker](https://www.docker.com/) and Docker Compose v2
- [`itkdev-docker-compose`](https://github.com/itk-dev/devops_itkdev-docker) on your `PATH`
- A working [Traefik](https://github.com/itk-dev/devops_itkdev-docker?tab=readme-ov-file#traefik) reverse proxy
- PHP 8.4 / Symfony 8
- Nginx + Traefik
- MariaDB
- Mailpit (outbound mail capture, local only)
- ITK Dev Docker development setup (`symfony-8` template)
- Taskrunner [Task](https://taskfile.dev/) (`Taskfile.yml`)

> **Status:** early development. The application is being scaffolded — see the
> [Base setup milestone](https://github.com/itk-dev/ai-lib/milestone/1) and
> [open issues](https://github.com/itk-dev/ai-lib/issues).

The platform is built up across milestones:

- **Catalog** — browse and list shared AI assistants with metadata.
- **Search & filtering** — full-text search, filter by tags/category, and sorting.
- **Assistant details** — full metadata and a readable configuration preview.
- **JSON export** — download an assistant as OpenWebUI-compatible JSON.
- **JSON import (share/upload flow)** — upload/paste OpenWebUI JSON to share an
  assistant, with validation and optional moderation.
- **User management** — login, roles/permissions, and profiles.
- **OpenWebUI tag/workflow** — AI-generated tags and OpenWebUI round-trip.

## Local development

Run `task` (or `task --list`) to see every available target.

## Common commands

```sh
# Run Composer inside the phpfpm container
task composer -- <command>

# Run a Symfony console command
task console -- <command>

# Apply PHP coding standards
task coding-standards-php-apply

# Lint Twig templates
task coding-standards-twig-check

# Format YAML
task coding-standards-yaml-apply

# Lint Markdown
task coding-standards-markdown-check

# Normalize composer.json
task coding-standards-composer-apply

# Run every coding-standards check
task coding-standards-check
```

For one-off commands without a dedicated task, fall back to the underlying
tools, e.g. `docker compose --profile dev run --rm prettier <args>` or
`itkdev-docker-compose <args>`.

> The commands below describe the intended ITK Dev standard setup. The actual
> Docker + Symfony scaffolding is added in
> [#1 Set up Docker + Symfony](https://github.com/itk-dev/ai-lib/issues/1);
> until that is merged, some commands will not yet be available.

### Requirements

- [Docker](https://www.docker.com/) and the
  [ITK Dev Docker setup](https://github.com/itk-dev/devops_itkdev-docker)
  (`itkdev-docker-compose`, Traefik)
- [Task](https://taskfile.dev/installation/)

### Getting started

```sh
# Clone the repository
git clone https://github.com/itk-dev/ai-lib.git
cd ai-lib

# List all available tasks
task

# Install site
task site-install

# Open the site
task open
```

The site is served through Traefik on a `*.local.itkdev.dk` domain (the exact
URL is printed by the start task).

## References

- **Estimation note:** <https://itk-dev.github.io/research-projects/projects/ai-bibliotek/estimeringsnotat>
- **Prototype & design direction:** <https://itk-dev.github.io/research-projects/projects/ai-bibliotek/mocks/index.html#/>

### Prototype routes

| View              | Route                                                                                                               |
|-------------------|---------------------------------------------------------------------------------------------------------------------|
| Home / front page | [`#/`](https://itk-dev.github.io/research-projects/projects/ai-bibliotek/mocks/index.html#/)                        |
| Login             | [`#/login`](https://itk-dev.github.io/research-projects/projects/ai-bibliotek/mocks/index.html#/login)              |
| Catalog / search  | [`#/search`](https://itk-dev.github.io/research-projects/projects/ai-bibliotek/mocks/index.html#/search)            |
| Assistant details | [`#/assistant/:id`](https://itk-dev.github.io/research-projects/projects/ai-bibliotek/mocks/index.html#/assistant/) |
| Upload / import   | [`#/upload`](https://itk-dev.github.io/research-projects/projects/ai-bibliotek/mocks/index.html#/upload)            |
| My assistants     | [`#/uploads`](https://itk-dev.github.io/research-projects/projects/ai-bibliotek/mocks/index.html#/uploads)          |
| Favorites         | [`#/favorites`](https://itk-dev.github.io/research-projects/projects/ai-bibliotek/mocks/index.html#/favorites)      |
| Collections       | [`#/collections`](https://itk-dev.github.io/research-projects/projects/ai-bibliotek/mocks/index.html#/collections)  |

> The prototype is a client-side mock (data stored locally in the browser),
> not production code.
