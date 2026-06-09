# Project conventions for Claude

This file documents project-specific patterns. Global preferences live in
`~/.claude/CLAUDE.md` and still apply.

## Twig templates

### Component-first

All non-trivial markup belongs in a component under `templates/components/`,
not inline in page or layout templates. Page templates (`templates/<page>/…`)
should read as a thin composition of `<twig:…>` calls.

We use **anonymous components** from `symfony/ux-twig-component` — no PHP
backing class. The component template alone defines the contract via
`{% props … %}`.

### Directory layout

Group components by domain under `templates/components/`. Nested namespaces
become nested directories:

```
templates/components/
    Eyebrow.html.twig                # <twig:Eyebrow>
    Hero.html.twig                   # <twig:Hero>
    Layout/SiteHeader.html.twig      # <twig:Layout:SiteHeader>
    Nav/Link.html.twig               # <twig:Nav:Link>
    Stats/List.html.twig             # <twig:Stats:List>
    Stats/Item.html.twig             # <twig:Stats:Item>
```

Use PascalCase for component file names (matches the `<twig:Name>` casing).
Singular vs plural follows whether the component is a container (`List`)
or a single item (`Item`).

### Component anatomy

Every component template has this shape:

```twig
{% props label, value, href = '#' %}
<a class="…" href="{{ href }}">
    <span class="…">{{ label }}</span>
    <span class="…">{{ value }}</span>
    {% block content %}{% endblock %}
</a>
```

Rules:

1. **Declare props** with `{% props … %}` on the first line. Required
   props have no default; optional props use `prop = 'value'`.
2. **Default slot** is `{% block content %}{% endblock %}` — content placed
   between `<twig:Foo>…</twig:Foo>` lands here.
3. **Named slots** use `{% block <name> %}{% endblock %}` and are filled
   from the call site with `<twig:block name="<name>">…</twig:block>`.
   Prefer named slots over `|raw` string props whenever the value contains
   HTML (e.g. an inline `<em>`).
4. Keep Tailwind utility classes inline. Design tokens (`text-ink`,
   `bg-surface`, `font-display`, …) come from the `@theme` block in
   `assets/styles/app.css`; do not introduce new CSS files for one-off
   styles.

### Dev-mode template markers

In the `dev` environment, every project template is wrapped at compile
time with HTML comments showing its path:

```html
<!-- components/Hero.html.twig -->
…component output…
<!-- /components/Hero.html.twig -->
```

This is automatic — do **not** add `{% if app.environment == 'dev' %}<!-- … -->{% endif %}`
lines inside templates. The injection is done by
`App\Twig\DevTemplateMarkerNodeVisitor` (in `src/Twig/`), registered
only in dev so prod output is unchanged.

For templates that `extends` another, the visitor wraps the content of
the `body` block specifically (the template's top-level content never
renders in that case). Page templates should therefore put their content
inside `{% block body %}…{% endblock %}` if they want the auto-marker.

### Calling components

```twig
<twig:Hero eyebrow="Del &amp; hjemtag · dansk offentlig AI">
    <twig:block name="heading">
        Et fælles bibliotek over <em class="italic text-primary">kommunale</em> AI-assistenter.
    </twig:block>
    <twig:block name="lead">…</twig:block>

    <twig:Stats:List>
        <twig:Stats:Item label="Assistenter" value="{{ stats.assistants }}" />
        <twig:Stats:Item label="Kommuner"    value="{{ stats.kommuner }}" />
    </twig:Stats:List>
</twig:Hero>
```

Self-close (`<twig:Foo … />`) when the component has no slot content.

### When not to extract a component

- A piece of markup used exactly once and unlikely to repeat. Inline it.
- A wrapper that adds no parameters and no semantics. The call site is
  clearer with the underlying utilities.

A component earns its place when it has a name, a contract (props /
slots), and at least one reason to be reused or replaced in isolation.

### Translations

The default locale is `da`. All user-facing strings (labels, buttons,
ARIA labels, placeholders, page titles, copy) live in a single file:

```
translations/messages.da.yaml
```

Keys are hierarchical dot-notation, grouped by area (`nav.*`, `frontpage.*`,
`search.*`, `layout.*`, …). Conventions:

1. **Components translate text props internally.** Each text-bearing
   prop defaults to a translation key, and the component applies `|trans`
   itself. Call sites pass keys, not Danish strings:

   ```twig
   {# Stats/Item.html.twig — component #}
   {% props label, value %}
   <div>
       <dt>{{ label|trans }}</dt>
       <dd>{{ value }}</dd>
   </div>

   {# Call site #}
   <twig:Stats:Item label="frontpage.stats.assistants" value="{{ stats.assistants }}" />
   ```

   Defaults that hold a translation key let a call site omit the prop:

   ```twig
   {% props label = 'nav.menu_label' %}
   <nav aria-label="{{ label|trans }}">…</nav>
   ```

2. **HTML in translations.** Keys whose value contains HTML get the
   `_html` suffix and are rendered with `|trans|raw` at the call site
   (the translation source is trusted YAML, no XSS risk):

   ```yaml
   frontpage:
       hero:
           heading_html: 'Et fælles bibliotek over <em class="italic text-primary">kommunale</em> AI-assistenter.'
   ```

3. **Placeholders use `%name%` syntax** and are filled at call time:

   ```yaml
   frontpage:
       title: '%brand% – forhåndsvisning'
   ```
   ```twig
   {{ 'frontpage.title'|trans({'%brand%': brand_name}) }}
   ```

4. **`SAMPLE_ASSISTANTS` and other placeholder content data is NOT
   translated** — only chrome and copy strings are. Sample data will be
   replaced by real persistence soon and isn't worth extracting.

5. `bin/console debug:translation da` reports keys passed as props as
   `unused` because the static scanner can't see dynamic |trans calls
   inside components. Verify usage by inspecting the rendered HTML
   instead.

### Brand identity (env-driven, not translated)

The brand name, tagline, and logo initials come from environment
variables, exposed as Twig globals:

| Env var | Twig global | Default (in `config/services.yaml`) |
|---|---|---|
| `BRAND_NAME` | `brand_name` | `AI Bibliotek` |
| `BRAND_TAGLINE` | `brand_tagline` | `del & hjemtag assistenter · prototype` |
| `BRAND_INITIALS` | `brand_initials` | `AI` |

Wiring lives in `config/packages/twig.yaml` (globals block, with
`default:` env processor) and `config/services.yaml` (parameter
defaults). Set the env vars in `.env` for committed defaults, or in
`.env.local` for per-machine overrides.

Use `{{ brand_name }}` directly in templates; do not look it up via
`|trans`. Brand identity is configuration, not localization.

### Stimulus + CSS hooks

When a component carries a `data-controller=`, `data-action=`,
`data-…-target=`, or a hand-rolled CSS class hook (e.g. `.nav-toggle`,
`.nav-toggle-bar`), leave a short `{# … #}` comment in the component
explaining why the attribute must stay verbatim — these are load-bearing
for JS controllers in `assets/controllers/` or CSS rules in
`assets/styles/app.css`.
