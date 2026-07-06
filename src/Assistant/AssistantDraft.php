<?php

declare(strict_types=1);

namespace App\Assistant;

/**
 * In-flight state carried across the three steps of the
 * "share an assistant" wizard.
 *
 * The DTO belongs to the `AssistantCreateFlowType` form flow.
 * Symfony's `SessionDataStorage` serialises it between requests
 * so a user paging back and forth keeps their JSON, extracted
 * metadata, and eventual review edits.
 *
 * The `step` property is the current cursor name, kept in sync
 * by Symfony's `PropertyPathStepAccessor` (wired via the flow's
 * `step_property_path` option). The three steps are `json`,
 * `metadata`, `receipt`. `createdAssistantId` is written by the
 * controller when the flow transitions past step 2 (metadata
 * → receipt), so step 3 can render a permalink and the
 * transition itself stays idempotent (a refresh mid-step-3
 * doesn't re-persist).
 */
final class AssistantDraft
{
    /**
     * Current step name — driven by Symfony's flow cursor via
     * the `step_property_path` option on the flow type.
     */
    public string $step = 'json';

    /**
     * Raw assistant config as the user pasted / uploaded it, in the
     * detected format. The adapter + `AssistantCreator::create()`
     * validate and parse it to the stored source dict at persist time.
     */
    public string $sourceConfig = '';

    public string $title = '';

    public string $description = '';

    public string $framework = 'openwebui';

    public string $languageModel = '';

    /**
     * @var list<string> zero or more tag names as typed on step 2
     */
    public array $tags = [];

    /**
     * ULID of the persisted assistant, written by the controller
     * on the metadata→receipt transition. Null until that
     * happens; non-null on step 3 so the template can build a
     * permalink.
     */
    public ?string $createdAssistantId = null;
}
