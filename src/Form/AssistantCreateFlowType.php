<?php

declare(strict_types=1);

namespace App\Form;

use App\Assistant\AssistantDraft;
use App\Assistant\OpenWebUiMetadataExtractor;
use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\DataStorage\SessionDataStorage;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\Type\NavigatorFlowType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Three-step "share an assistant" wizard.
 *
 * Uses Symfony's {@see AbstractFlowType} multi-step form support
 * so state carrying, navigation buttons (Previous / Next /
 * Finish), and validation-group scoping come from the framework
 * rather than hand-rolled session plumbing.
 *
 * Steps:
 *
 * 1. `json`     — user pastes / uploads the OpenWebUI export.
 *                 On successful submit the step-1 `POST_SUBMIT`
 *                 listener runs {@see OpenWebUiMetadataExtractor}
 *                 so step 2 opens with title / description /
 *                 language model / tags pre-filled.
 * 2. `metadata` — review + edit the extracted metadata. Full
 *                 validation of the `metadata` group runs here.
 * 3. `receipt`  — render-only "assistant delt" page with a
 *                 permalink to the created row. The controller
 *                 persists the assistant on the 2 → 3 transition
 *                 and stashes the id on the DTO.
 *
 * State lives in the session (`SessionDataStorage`) under the
 * `assistant_new_flow` key. `auto_reset = true` (the default)
 * means the session slot is cleared when the flow finishes, so
 * hitting `/assistant/new` again after a save starts fresh.
 */
final class AssistantCreateFlowType extends AbstractFlowType
{
    /**
     * Session key {@see SessionDataStorage} uses for this flow.
     * A distinct key namespace keeps the DTO isolated from any
     * future flow the app might add.
     */
    public const string SESSION_KEY = 'assistant_new_flow';

    /**
     * @param RequestStack                $requestStack backing the session storage
     * @param OpenWebUiMetadataExtractor $extractor    pre-populates step 2 from the step 1 JSON
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly OpenWebUiMetadataExtractor $extractor,
    ) {
    }

    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $builder
            ->addStep('json', AssistantJsonStepType::class)
            ->addStep('metadata', AssistantMetadataStepType::class)
            ->addStep('receipt', AssistantReceiptStepType::class)
            ->add('navigator', NavigatorFlowType::class)
        ;

        // On step 1 → step 2, the DTO's raw JSON is fresh. Extract
        // suggested title / description / language model / tags
        // into the still-empty step 2 fields so the user sees a
        // filled form instead of blanks. Fires against the whole
        // flow after the step 1 form submit resolves.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $draft = $event->getData();
            if ($draft instanceof AssistantDraft && 'json' === $draft->step) {
                $this->extractor->extractInto($draft);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AssistantDraft::class,
            'step_property_path' => 'step',
            'data_storage' => new SessionDataStorage(self::SESSION_KEY, $this->requestStack),
        ]);
    }
}
