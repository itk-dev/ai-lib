# AI Bibliotek (ai-lib)

A shared catalog of AI assistants for the Danish public sector. `ai-lib`
lets contributors export, share, search, and import assistants — using the
OpenWebUI JSON format as the interchange — and provides moderation and
metadata around what ends up in the catalog.

## Tech stack

- PHP 8.4 / Symfony 8
- Nginx + Traefik
- MariaDB
- Mailpit (outbound mail capture, local only)
- ITK Dev Docker development setup (`symfony-8` template)

> **Status:** early development. The application is being scaffolded — see the
> [Base setup milestone](https://github.com/itk-dev/ai-lib/milestone/1) and
> [open issues](https://github.com/itk-dev/ai-lib/issues).

## Features

The platform is built up across milestones:

- **Catalog** — browse and list shared AI assistants with metadata.
- **Search & filtering** — full-text search, filter by tags/category, and sorting.
- **Assistant details** — full metadata and a readable configuration preview.
- **JSON export** — download an assistant as OpenWebUI-compatible JSON.
- **JSON import (share/upload flow)** — upload/paste OpenWebUI JSON to share an
  assistant, with validation and optional moderation.
- **User management** — login, roles/permissions, and profiles.
- **OpenWebUI tag/workflow** — AI-generated tags and OpenWebUI round-trip.

## Tech stack

- **Backend:** [Symfony](https://symfony.com/) (PHP)
- **Local environment:** Docker via the ITK Dev Docker setup
  ([itkdev-docker-compose](https://github.com/itk-dev/devops_itkdev-docker)),
  fronted by Traefik
- **Task runner:** [Task](https://taskfile.dev/) (`Taskfile.yml`)

## Local development

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

# Start the site (build containers, install dependencies, run migrations)
task dev:up

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
