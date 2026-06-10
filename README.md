# ai-lib

A Symfony 8 application built on top of the ITK Dev Docker development setup.

## Requirements

- [Docker](https://www.docker.com/) and Docker Compose v2
- [`itkdev-docker-compose`](https://github.com/itk-dev/devops_itkdev-docker) on your `PATH`
- [Task](https://taskfile.dev) (`task` on `PATH`) — the project uses
  `Taskfile.yml` as the entry point for common developer commands
- A working [Traefik](https://github.com/itk-dev/devops_itkdev-docker?tab=readme-ov-file#traefik) reverse proxy

## Local development

```sh
# Start the shared Traefik reverse proxy (idempotent; safe to rerun)
itkdev-docker-compose traefik:start

# Pull images, bring the stack up, and install Composer dependencies
task site-install

# Open the site
task site-open
```

The site is served at <https://ai-lib.local.itkdev.dk>. Mail is captured by
[Mailpit](https://github.com/axllent/mailpit) and available at
<https://mail-ai-lib.local.itkdev.dk>.

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

## References

- [ITK Dev Docker templates](https://github.com/itk-dev/devops_itkdev-docker)
- [Symfony documentation](https://symfony.com/doc)
