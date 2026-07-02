# Example import files

One importable example per supported format, all describing the same
assistant (*Referathjælper*) so the formats can be compared side by side.
Upload any of them at `/assistant/new`; the wizard detects the format
automatically.

| File | Format | Notes |
| --- | --- | --- |
| [`native.json`](native.json) | AI-reolen native | Lossless — carries every canonical field, including tags and conversation starters. |
| [`openwebui.json`](openwebui.json) | Open WebUI | Array-of-one model export with `params.system`, `meta.tags`, and suggestion prompts. |
| [`openai.json`](openai.json) | OpenAI Assistants | Tags travel in `metadata.tags` as a comma-separated list. |
| [`librechat.json`](librechat.json) | LibreChat preset | Label from `chatGptLabel`, system prompt from `promptPrefix`. |
| [`ollama.modelfile`](ollama.modelfile) | Ollama Modelfile | Text DSL; carries only `FROM` and `SYSTEM` (no name/tags). |

The base model is `gpt-4o` (or `llama3.2` for Ollama). On export to a
system where the chosen model has no equivalent — e.g. `gpt-4o` to
Ollama — the name is passed through unchanged and a non-blocking warning
is shown; it is never silently swapped for a different model.
