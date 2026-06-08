# ai-lib

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

## Contributing

Branching, commits, CHANGELOG, and PR conventions for this project will be
documented in `CONTRIBUTING.md` (tracked in
[#9](https://github.com/itk-dev/ai-lib/issues/9)). Until then, follow the
patterns visible in existing branches and PRs.

## References

- [ITK Dev Docker templates](https://github.com/itk-dev/devops_itkdev-docker)
- [Symfony documentation](https://symfony.com/doc)
- [OpenWebUI](https://openwebui.com/)
