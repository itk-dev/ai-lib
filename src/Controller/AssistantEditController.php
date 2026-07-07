<?php

declare(strict_types=1);

namespace App\Controller;

use App\Assistant\AssistantDraft;
use App\Assistant\AssistantEditor;
use App\Assistant\Format\FormatAdapterRegistry;
use App\Entity\Assistant;
use App\Entity\Tag;
use App\Form\AssistantCreateFlowType;
use App\Security\Voter\EditAssistantVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Flow\DataStorage\SessionDataStorage;
use Symfony\Component\Form\Flow\FormFlowInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Serves the "edit an assistant" three-step wizard.
 *
 * Reuses {@see AssistantCreateFlowType}'s step types + templates but
 * seeds the draft from the persisted entity and routes the
 * `metadata → receipt` transition through
 * {@see AssistantEditor::update()} so the row is updated in place
 * rather than duplicated. Each edit gets its own session slot
 * (`assistant_edit_flow.<id>`) so a curator editing one assistant
 * never picks up a stale draft from an unrelated one.
 */
final class AssistantEditController extends AbstractController
{
    /**
     * @param FormatAdapterRegistry $formats      backs the step-1 upload progress + acts as the format id source for the flow
     * @param AssistantEditor       $editor       applies the DTO to the entity on the `metadata → receipt` transition
     * @param RequestStack          $requestStack backs the per-assistant session data storage that isolates edits from each other and from the create flow
     */
    public function __construct(
        private readonly FormatAdapterRegistry $formats,
        private readonly AssistantEditor $editor,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Serve the edit wizard for `$assistant`.
     *
     * Auth is gated by {@see EditAssistantVoter::EDIT}: the assistant's
     * `createdBy` blame user and site admins may edit; everyone else
     * hits 403. A fresh `GET` after a completed edit resets the
     * per-assistant session slot so the same URL doesn't get stuck on
     * the receipt.
     *
     * @param Request   $request   the current HTTP request handled by the flow
     * @param Assistant $assistant the row being edited, resolved by `MapEntity`
     *
     * @return Response the rendered wizard step, or the receipt after a successful `metadata → receipt` transition
     */
    #[Route(path: '/assistant/{id}/edit', name: 'app_assistant_edit', methods: ['GET', 'POST'])]
    #[IsGranted(EditAssistantVoter::EDIT, subject: 'assistant')]
    public function edit(Request $request, #[MapEntity] Assistant $assistant): Response
    {
        $assistantId = (string) $assistant->getId();
        $dataStorage = new SessionDataStorage('assistant_edit_flow.'.$assistantId, $this->requestStack);

        $flow = $this->buildFlow($assistant, $dataStorage);

        // A fresh GET landing on the page after a completed edit (session
        // still holds a draft with `createdAssistantId` set) should start
        // over from step 1 rather than showing the previous receipt. The
        // flow's `auto_reset` only fires when the user clicks Finish, so
        // reset explicitly for anyone who navigates away and comes back.
        if ('GET' === $request->getMethod()) {
            $stored = $flow->getData();
            if ($stored instanceof AssistantDraft && null !== $stored->createdAssistantId) {
                $flow->reset();
                $flow = $this->buildFlow($assistant, $dataStorage);
            }
        }

        $flow->handleRequest($request);
        $stepForm = $flow->getStepForm();
        \assert($stepForm instanceof FormFlowInterface);

        $draft = $stepForm->getData();
        if ($draft instanceof AssistantDraft
            && 'receipt' === $draft->step
            && null === $draft->createdAssistantId
        ) {
            $updated = $this->editor->update(
                $assistant,
                $draft->title,
                $draft->description,
                $draft->languageModel,
                $draft->framework,
                $draft->tags,
                $draft->sourceConfig,
                $draft->organizationId,
                $draft->tagline,
                $draft->knowledgeDescription,
                $draft->dataSensitivity,
            );
            $draft->createdAssistantId = (string) $updated->getId();
            $stepForm->getConfig()->getDataStorage()->save($draft);
        }

        return $this->renderStep($stepForm);
    }

    /**
     * Build a flow instance seeded from `$assistant` and wired to
     * `$dataStorage`.
     *
     * Extracted so the reset branch above can rebuild with the same
     * seed after clearing the session slot without duplicating the
     * hydration logic.
     *
     * @param Assistant          $assistant   the row whose values seed the draft
     * @param SessionDataStorage $dataStorage per-assistant session slot for the flow
     *
     * @return FormFlowInterface the constructed and typed flow
     */
    private function buildFlow(Assistant $assistant, SessionDataStorage $dataStorage): FormFlowInterface
    {
        $flow = $this->createForm(
            AssistantCreateFlowType::class,
            $this->hydrateDraft($assistant),
            ['data_storage' => $dataStorage],
        );
        \assert($flow instanceof FormFlowInterface);

        return $flow;
    }

    /**
     * Seed an {@see AssistantDraft} from the persisted entity.
     *
     * `editingAssistantId` is what signals the prefiller to leave the
     * draft alone and the controller to route through
     * {@see AssistantEditor::update()}. The source config is
     * re-serialised as pretty-printed JSON so step 1's textarea shows
     * a copy of the stored dict — good enough for JSON formats to
     * round-trip through the adapter's parser on submit.
     *
     * @param Assistant $assistant the row whose values seed the draft
     *
     * @return AssistantDraft the pre-populated draft
     */
    private function hydrateDraft(Assistant $assistant): AssistantDraft
    {
        $draft = new AssistantDraft();
        $draft->editingAssistantId = (string) $assistant->getId();
        $draft->title = $assistant->getTitle();
        $draft->description = $assistant->getDescription();
        $draft->framework = $assistant->getFramework();
        $draft->languageModel = $assistant->getLanguageModel();
        $draft->tags = array_map(
            static fn (Tag $tag): string => $tag->getName(),
            $assistant->getTags()->toArray(),
        );
        $draft->organizationId = null !== $assistant->getOrganization()
            ? (string) $assistant->getOrganization()->getId()
            : null;
        $draft->tagline = $assistant->getTagline() ?? '';
        $draft->knowledgeDescription = $assistant->getKnowledgeDescription() ?? '';
        $draft->dataSensitivity = $assistant->getDataSensitivity();
        $draft->sourceConfig = json_encode(
            $assistant->getSourceConfig() ?? new \stdClass(),
            \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
        );

        return $draft;
    }

    /**
     * Render the current step through the shared wizard template.
     *
     * @param FormFlowInterface $stepForm the flow's current step form
     *
     * @return Response the rendered `assistant/edit.html.twig`
     */
    private function renderStep(FormFlowInterface $stepForm): Response
    {
        $status = Response::HTTP_OK;
        if ($stepForm->isSubmitted() && !$stepForm->isValid()) {
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return $this->render('assistant/edit.html.twig', [
            'flow' => $stepForm->createView(),
            'draft' => $stepForm->getData(),
            'checks' => $this->configChecks(),
        ], new Response('', $status));
    }

    /**
     * The union of every registered format's validation checks, in
     * declared order — same shape the create controller exposes to
     * step 1's upload progress bar.
     *
     * @return list<string> the deduplicated check identifiers
     */
    private function configChecks(): array
    {
        $checks = [];
        foreach (array_keys($this->formats->all()) as $id) {
            foreach ($this->formats->get($id)->getChecks() as $check) {
                $checks[$check] = true;
            }
        }

        return array_keys($checks);
    }
}
