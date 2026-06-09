# ai-lib

A Symfony 8 application built on top of the ITK Dev Docker development setup.

## Requirements

- [Docker](https://www.docker.com/) and Docker Compose v2
- [`itkdev-docker-compose`](https://github.com/itk-dev/devops_itkdev-docker) on your `PATH`
- A working [Traefik](https://github.com/itk-dev/devops_itkdev-docker?tab=readme-ov-file#traefik) reverse proxy

## Local development

```sh
# Start the shared Traefik reverse proxy (idempotent; safe to rerun)
itkdev-docker-compose traefik:start

# Bring up the project containers
docker compose up --detach

# Open the site
itkdev-docker-compose open
```

The site is served at <https://ai-lib.local.itkdev.dk>. Mail is captured by
[Mailpit](https://github.com/axllent/mailpit) and available at
<https://mail-ai-lib.local.itkdev.dk>.

## Common commands

```sh
# Run Composer inside the phpfpm container
itkdev-docker-compose composer <command>

# Run any PHP command inside the phpfpm container
itkdev-docker-compose php <command>

# Apply PHP coding standards
itkdev-docker-compose vendor/bin/php-cs-fixer fix

# Lint Twig templates
itkdev-docker-compose vendor/bin/twig-cs-fixer lint

# Format YAML
docker compose --profile dev run --rm prettier '**/*.{yml,yaml}' --write

# Lint Markdown
docker compose --profile dev run --rm markdownlint markdownlint '**/*.md'

# Normalize composer.json
itkdev-docker-compose composer normalize
```

## Frontend assets

The project uses [Tailwind CSS](https://tailwindcss.com/) on top of
Symfony's [AssetMapper](https://symfony.com/doc/current/frontend/asset_mapper.html),
with [Stimulus](https://stimulus.hotwired.dev/) for behaviour. There is
no Node toolchain — the Tailwind binary is managed by
[`symfonycasts/tailwind-bundle`](https://github.com/SymfonyCasts/tailwind-bundle).
See [ADR 002](docs/adr/002-frontend-tooling.md) for the rationale.

```sh
# One-time: download the Tailwind binary (also runs lazily on first build)
itkdev-docker-compose php bin/console tailwind:build

# Build the compiled stylesheet
itkdev-docker-compose php bin/console tailwind:build

# Watch source files and rebuild on change (development)
itkdev-docker-compose php bin/console tailwind:build --watch

# Compile and version the full importmap + assets (production)
itkdev-docker-compose php bin/console asset-map:compile

# Inspect what AssetMapper sees
itkdev-docker-compose php bin/console debug:asset-map
```

> **Heads-up:** there is no live Tailwind watcher running by default, and
> `cache:clear` does **not** rebuild the stylesheet. After editing a
> template that introduces a utility class not already in use (e.g.
> `pt-2`, `grid-cols-1`), run `tailwind:build` — or keep a
> `tailwind:build --watch` terminal open while you style.

Source files live under [`assets/`](assets):

- `assets/app.js` — JavaScript entrypoint, boots Stimulus.
- `assets/styles/app.css` — Tailwind entrypoint (`@import "tailwindcss";`).
- `assets/controllers/` — Stimulus controllers, auto-registered by
  filename (`nav_toggle_controller.js` → `data-controller="nav-toggle"`).

## References

- [ITK Dev Docker templates](https://github.com/itk-dev/devops_itkdev-docker)
- [Symfony documentation](https://symfony.com/doc)
