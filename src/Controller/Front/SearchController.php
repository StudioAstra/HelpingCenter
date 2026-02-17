<?php

namespace App\Controller\Front;

use App\Service\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/recherche', name: 'front_search')]
    public function search(Request $request, SearchService $searchService): Response
    {
        $query = $request->query->get('q', '');
        $results = $searchService->search($query);

        return $this->render('front/search.html.twig', [
            'query' => $query,
            'results' => $results,
        ]);
    }

    #[Route('/api/search', name: 'front_search_ajax', methods: ['GET'])]
    public function ajaxSearch(Request $request, SearchService $searchService): JsonResponse
    {
        $query = $request->query->get('q', '');
        $results = $searchService->search($query, 5);

        $data = [];
        foreach ($results as $article) {
            $section = $article->getSection();
            $subsection = $article->getSubsection();
            if (!$section) {
                continue;
            }
            $data[] = [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'section' => $section->getTitle(),
                'subsection' => $subsection?->getTitle(),
                'url' => $subsection
                    ? $this->generateUrl('front_article', [
                        'sectionSlug' => $section->getSlug(),
                        'subsectionSlug' => $subsection->getSlug(),
                        'articleSlug' => $article->getSlug(),
                    ])
                    : $this->generateUrl('front_article_section', [
                        'sectionSlug' => $section->getSlug(),
                        'articleSlug' => $article->getSlug(),
                    ]),
            ];
        }

        return new JsonResponse($data);
    }
}
