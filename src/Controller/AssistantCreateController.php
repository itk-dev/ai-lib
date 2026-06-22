<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Assistant;
use App\Validator\OpenWebUiConfigValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AssistantCreateController extends AbstractController
{
    public function __construct(
        private readonly OpenWebUiConfigValidator $validator,
        private readonly EntityManagerInterface $entityManager,
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
                return $this->render('assistant/new.html.twig', [
                    'submitted' => $submitted,
                    'errors' => ['assistant.new.error.invalid_token'],
                ], new Response('', Response::HTTP_FORBIDDEN));
            }

            $result = $this->validator->validate($submitted['openwebui_config']);
            if (!$result->isValid()) {
                $errors = $result->getErrors();
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            } else {
                $assistant = new Assistant(
                    title: $submitted['title'],
                    description: $submitted['description'],
                    languageModel: $submitted['language_model'],
                    framework: $submitted['framework'],
                    tags: $submitted['tags'],
                );
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode($submitted['openwebui_config'], associative: true, flags: \JSON_THROW_ON_ERROR);
                $assistant->setOpenwebuiConfig($decoded);

                $this->entityManager->persist($assistant);
                $this->entityManager->flush();

                return $this->redirectToRoute('app_assistant_show', ['id' => (int) $assistant->getId()]);
            }
        }

        return $this->render('assistant/new.html.twig', [
            'submitted' => $submitted,
            'errors' => $errors,
        ], new Response('', $status));
    }

    #[Route(path: '/assistant/new/validate-config', name: 'app_assistant_new_validate_config', methods: ['POST'])]
    public function validateConfig(Request $request): JsonResponse
    {
        /** @var array{json?: string} $payload */
        $payload = json_decode((string) $request->getContent(), associative: true) ?? [];
        $json = (string) ($payload['json'] ?? '');

        $result = $this->validator->validate($json);

        return new JsonResponse([
            'valid' => $result->isValid(),
            'errors' => $result->getErrors(),
        ]);
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
