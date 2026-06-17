<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AssistantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AssistantCatalogController extends AbstractController
{
    private const PER_PAGE = 12;

    public function __construct(private readonly AssistantRepository $assistants)
    {
    }

    #[Route('/search', name: 'app_assistant_catalog', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $languageModels = $this->stringList($request, 'language_model');
        $frameworks = $this->stringList($request, 'framework');
        $page = max(1, $request->query->getInt('page', 1));

        $paginator = $this->assistants->findPaginated($languageModels, $frameworks, $page, self::PER_PAGE);
        $total = \count($paginator);
        $pageCount = max(1, (int) ceil($total / self::PER_PAGE));

        return $this->render('catalog/index.html.twig', [
            'results' => $paginator,
            'total' => $total,
            'page' => $page,
            'pageCount' => $pageCount,
            'perPage' => self::PER_PAGE,
            'filters' => [
                'language_model' => $languageModels,
                'framework' => $frameworks,
            ],
            'facets' => [
                'language_model' => $this->assistants->languageModelFacetCounts(),
                'framework' => $this->assistants->frameworkFacetCounts(),
            ],
        ]);
    }

    private function stringList(Request $request, string $key): array
    {
        $raw = $request->query->all($key);
        $values = [];
        foreach ($raw as $value) {
            if (\is_string($value) && '' !== $value) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
