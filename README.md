# ai-lib

A Symfony 8 application built on the ITK Dev Docker setup.

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

The site is served at `https://ai-lib.local.itkdev.dk`. Mail is captured by
[Mailpit](https://github.com/axllent/mailpit) and available at
`https://mail-ai-lib.local.itkdev.dk`.

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

## References

- [ITK Dev Docker templates](https://github.com/itk-dev/devops_itkdev-docker)
- [Symfony documentation](https://symfony.com/doc)
