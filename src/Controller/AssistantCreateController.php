<?php

declare(strict_types=1);

namespace App\Controller;

use App\Assistant\AssistantCreator;
use App\Assistant\AssistantDraft;
use App\Form\AssistantCreateFlowType;
use App\Validator\OpenWebUiConfigValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Flow\FormFlowInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AssistantCreateController extends AbstractController
{
    public function __construct(
        private readonly OpenWebUiConfigValidator $validator,
        private readonly AssistantCreator $creator,
    ) {
    }

    #[Route(path: '/assistant/new', name: 'app_assistant_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        // Seed the flow with a fresh draft — SessionDataStorage
        // replaces it with the persisted DTO on subsequent requests,
        // but the initial construction always needs an object to
        // read the `step` property off.
        $flow = $this->createForm(AssistantCreateFlowType::class, new AssistantDraft());
        \assert($flow instanceof FormFlowInterface);

        // A fresh GET landing on the page after a completed run
        // (session still holds a draft with a persisted id) should
        // start over from step 1 rather than showing the previous
        // receipt. The flow's `auto_reset` only fires when the user
        // clicks Finish, so we reset explicitly for anyone who
        // navigates away and comes back.
        if ('GET' === $request->getMethod()) {
            $stored = $flow->getData();
            if ($stored instanceof AssistantDraft && null !== $stored->createdAssistantId) {
                $flow->reset();
                $flow = $this->createForm(AssistantCreateFlowType::class, new AssistantDraft());
                \assert($flow instanceof FormFlowInterface);
            }
        }

        // handleRequest runs the current step's submit + validation.
        // getStepForm() then reads the cursor — if the submit was
        // valid it moves the cursor forward and returns a fresh
        // form for the next step, otherwise it returns the same
        // step's form for re-rendering with errors.
        $flow->handleRequest($request);
        $stepForm = $flow->getStepForm();
        \assert($stepForm instanceof FormFlowInterface);

        // The transition from `metadata` → `receipt` is the commit
        // moment: persist the assistant and stash its id on the
        // DTO. Guarded on `createdAssistantId` so a page refresh
        // in step 3 doesn't re-persist. Step 1's `Assert\Json` and
        // the metadata step's `NotBlank` constraints cover every
        // rejection the deeper `AssistantCreator::create()` would
        // otherwise catch, so no `InvalidAssistantInputException`
        // catch is needed here.
        $draft = $stepForm->getData();
        if ($draft instanceof AssistantDraft
            && 'receipt' === $draft->step
            && null === $draft->createdAssistantId
        ) {
            $assistant = $this->creator->create(
                $draft->title,
                $draft->description,
                $draft->languageModel,
                $draft->framework,
                $draft->tags,
                $draft->openwebuiConfig,
            );
            $draft->createdAssistantId = (string) $assistant->getId();

            // The flow saves the DTO during handleRequest, before
            // we set createdAssistantId. Persist the mutation
            // ourselves so a subsequent GET can tell the wizard
            // has finished and reset the session slot.
            $stepForm->getConfig()->getDataStorage()->save($draft);
        }

        return $this->renderStep($stepForm);
    }

    #[Route(path: '/assistant/new/validate-config', name: 'app_assistant_new_validate_config', methods: ['POST'])]
    public function validateConfig(Request $request): JsonResponse
    {
        /** @var array{json?: string, check?: string} $payload */
        $payload = json_decode((string) $request->getContent(), associative: true) ?? [];
        $json = (string) ($payload['json'] ?? '');
        $check = (string) ($payload['check'] ?? '');

        if (!\in_array($check, $this->validator->getChecks(), true)) {
            return new JsonResponse(
                ['valid' => false, 'errors' => [\sprintf('Unknown check "%s".', $check)]],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $result = $this->validator->runCheck($check, $json);

        return new JsonResponse([
            'valid' => $result->isValid(),
            'errors' => $result->getErrors(),
        ]);
    }

    private function renderStep(FormFlowInterface $stepForm): Response
    {
        $status = Response::HTTP_OK;
        if ($stepForm->isSubmitted() && !$stepForm->isValid()) {
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return $this->render('assistant/new.html.twig', [
            'flow' => $stepForm->createView(),
            'draft' => $stepForm->getData(),
            'checks' => $this->validator->getChecks(),
        ], new Response('', $status));
    }
}
