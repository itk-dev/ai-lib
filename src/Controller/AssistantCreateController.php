<?php

declare(strict_types=1);

namespace App\Controller;

use App\Assistant\AssistantCreator;
use App\Assistant\InvalidAssistantInputException;
use App\Validator\OpenWebUiConfigValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        $submitted = $this->emptySubmitted();
        $errors = [];
        $status = Response::HTTP_OK;

        if ('POST' === $request->getMethod()) {
            $submitted = $this->readSubmitted($request);

            if (!$this->isCsrfTokenValid('assistant-create', (string) $request->request->get('_token'))) {
                return $this->renderForm($submitted, ['assistant.new.error.invalid_token'], Response::HTTP_FORBIDDEN);
            }

            try {
                $assistant = $this->creator->create(
                    $submitted['title'],
                    $submitted['description'],
                    $submitted['language_model'],
                    $submitted['framework'],
                    $submitted['tags'],
                    $submitted['openwebui_config'],
                );

                return $this->redirectToRoute('app_assistant_show', ['id' => (int) $assistant->getId()]);
            } catch (InvalidAssistantInputException $e) {
                $errors = $e->getErrors();
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            }
        }

        return $this->renderForm($submitted, $errors, $status);
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

    /**
     * @param array{title: string, description: string, language_model: string, framework: string, tags: list<string>, openwebui_config: string} $submitted
     * @param list<string>                                                                                                                       $errors
     */
    private function renderForm(array $submitted, array $errors, int $status): Response
    {
        return $this->render('assistant/new.html.twig', [
            'submitted' => $submitted,
            'errors' => $errors,
            'checks' => $this->validator->getChecks(),
        ], new Response('', $status));
    }

    /**
     * @return array{title: string, description: string, language_model: string, framework: string, tags: list<string>, openwebui_config: string}
     */
    private function emptySubmitted(): array
    {
        return [
            'title' => '',
            'description' => '',
            'language_model' => '',
            'framework' => '',
            'tags' => [],
            'openwebui_config' => '',
        ];
    }

    /**
     * @return array{title: string, description: string, language_model: string, framework: string, tags: list<string>, openwebui_config: string}
     */
    private function readSubmitted(Request $request): array
    {
        $tagsRaw = (string) $request->request->get('tags', '');
        $tags = array_values(array_filter(array_map('trim', explode(',', $tagsRaw)), static fn (string $t): bool => '' !== $t));

        return [
            'title' => (string) $request->request->get('title', ''),
            'description' => (string) $request->request->get('description', ''),
            'language_model' => (string) $request->request->get('language_model', ''),
            'framework' => (string) $request->request->get('framework', ''),
            'tags' => $tags,
            'openwebui_config' => (string) $request->request->get('openwebui_config', ''),
        ];
    }
}
