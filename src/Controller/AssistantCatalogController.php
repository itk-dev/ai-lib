<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalog\CatalogCriteria;
use App\Http\QueryStringList;
use App\Pagination\PageMetadata;
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
    ) {
    }

    #[Route('/search', name: 'app_assistant_catalog', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $criteria = CatalogCriteria::fromRequest($request, $this->queryStringList);
        $page = max(1, $request->query->getInt('page', 1));

        $paginator = $this->assistants->findPaginated($criteria, $page, self::PER_PAGE);
        $metadata = PageMetadata::fromPaginator($paginator, $page, self::PER_PAGE);

        return $this->render('catalog/index.html.twig', [
            'results' => $paginator,
            'criteria' => $criteria,
            'metadata' => $metadata,
            'facets' => [
                'language_model' => $this->assistants->languageModelFacetCounts(),
                'framework' => $this->assistants->frameworkFacetCounts(),
                'tag' => $this->assistants->tagFacetCounts(),
            ],
        ]);
    }
}
