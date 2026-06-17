<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\QueryStringList;
use App\Pagination\PaginationCalculator;
use App\Repository\AssistantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AssistantCatalogController extends AbstractController
{
    private const PER_PAGE = 12;

    public function __construct(
        private readonly AssistantRepository $assistants,
        private readonly QueryStringList $queryStringList,
        private readonly PaginationCalculator $pagination,
    ) {
    }

    #[Route('/search', name: 'app_assistant_catalog', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $languageModels = $this->queryStringList->fromRequest($request, 'language_model');
        $frameworks = $this->queryStringList->fromRequest($request, 'framework');
        $page = max(1, $request->query->getInt('page', 1));

        $paginator = $this->assistants->findPaginated($languageModels, $frameworks, $page, self::PER_PAGE);
        $metadata = $this->pagination->fromPaginator($paginator, $page, self::PER_PAGE);

        return $this->render('catalog/index.html.twig', [
            'results' => $paginator,
            'total' => $metadata->total,
            'page' => $metadata->page,
            'pageCount' => $metadata->pageCount,
            'perPage' => $metadata->perPage,
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
}
