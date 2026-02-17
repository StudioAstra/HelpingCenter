<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Entity\Section;
use App\Service\ArticleRenderer;
use App\Service\SearchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
class ApiController extends AbstractController
{
    #[Route('/sections', name: 'api_sections', methods: ['GET'])]
    public function sections(EntityManagerInterface $em): JsonResponse
    {
        $sections = $em->getRepository(Section::class)->findBy([], ['position' => 'ASC']);

        $data = [];
        foreach ($sections as $section) {
            $sectionData = [
                'id' => $section->getId(),
                'title' => $section->getTitle(),
                'slug' => $section->getSlug(),
                'description' => $section->getDescription(),
                'icon' => $section->getIcon(),
                'subsections' => [],
            ];

            foreach ($section->getSubsections() as $sub) {
                $sectionData['subsections'][] = [
                    'id' => $sub->getId(),
                    'title' => $sub->getTitle(),
                    'slug' => $sub->getSlug(),
                    'description' => $sub->getDescription(),
                    'icon' => $sub->getIcon(),
                ];
            }

            $data[] = $sectionData;
        }

        return new JsonResponse($data);
    }

    #[Route('/sections/{slug}', name: 'api_section_detail', methods: ['GET'])]
    public function sectionDetail(string $slug, EntityManagerInterface $em): JsonResponse
    {
        $section = $em->getRepository(Section::class)->findOneBy(['slug' => $slug]);

        if (!$section) {
            return new JsonResponse(['error' => 'Section non trouvée.'], 404);
        }

        $data = [
            'id' => $section->getId(),
            'title' => $section->getTitle(),
            'slug' => $section->getSlug(),
            'description' => $section->getDescription(),
            'icon' => $section->getIcon(),
            'subsections' => [],
        ];

        foreach ($section->getSubsections() as $sub) {
            $subData = [
                'id' => $sub->getId(),
                'title' => $sub->getTitle(),
                'slug' => $sub->getSlug(),
                'description' => $sub->getDescription(),
                'articles' => [],
            ];

            foreach ($sub->getArticles() as $article) {
                if ($article->isPublished()) {
                    $subData['articles'][] = [
                        'id' => $article->getId(),
                        'title' => $article->getTitle(),
                        'slug' => $article->getSlug(),
                    ];
                }
            }

            $data['subsections'][] = $subData;
        }

        return new JsonResponse($data);
    }

    #[Route('/articles/{id}', name: 'api_article_detail', methods: ['GET'])]
    public function articleDetail(
        Article $article,
        ArticleRenderer $renderer,
    ): JsonResponse {
        if (!$article->isPublished()) {
            return new JsonResponse(['error' => 'Article non trouvé.'], 404);
        }

        $subsection = $article->getSubsection();
        $section = $subsection->getSection();

        return new JsonResponse([
            'id' => $article->getId(),
            'title' => $article->getTitle(),
            'slug' => $article->getSlug(),
            'content' => $article->getContent(),
            'html' => $renderer->renderBlocks($article->getContent()),
            'headings' => $renderer->extractHeadings($article->getContent()),
            'section' => [
                'title' => $section->getTitle(),
                'slug' => $section->getSlug(),
            ],
            'subsection' => [
                'title' => $subsection->getTitle(),
                'slug' => $subsection->getSlug(),
            ],
            'published_at' => $article->getPublishedAt()?->format('c'),
            'updated_at' => $article->getUpdatedAt()->format('c'),
        ]);
    }

    #[Route('/search', name: 'api_search', methods: ['GET'])]
    public function search(Request $request, SearchService $searchService): JsonResponse
    {
        $query = $request->query->get('q', '');
        $results = $searchService->search($query);

        $data = [];
        foreach ($results as $article) {
            $subsection = $article->getSubsection();
            $section = $subsection->getSection();
            $data[] = [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'section' => $section->getTitle(),
                'subsection' => $subsection->getTitle(),
                'slug' => $article->getSlug(),
                'section_slug' => $section->getSlug(),
                'subsection_slug' => $subsection->getSlug(),
            ];
        }

        return new JsonResponse($data);
    }
}
