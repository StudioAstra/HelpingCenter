<?php

namespace App\Controller\Front;

use App\Entity\Article;
use App\Entity\ArticleFeedback;
use App\Entity\Section;
use App\Entity\Subsection;
use App\Service\ArticleRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ArticleController extends AbstractController
{
    #[Route(
        '/{sectionSlug}/{subsectionSlug}/{articleSlug}',
        name: 'front_article',
        priority: -20,
        requirements: ['subsectionSlug' => '(?!article$)[^/]+']
    )]
    public function showFromSubsection(
        string $sectionSlug,
        string $subsectionSlug,
        string $articleSlug,
        EntityManagerInterface $em,
        ArticleRenderer $renderer,
    ): Response {
        $section = $em->getRepository(Section::class)->findOneBy(['slug' => $sectionSlug]);
        if (!$section) {
            throw new NotFoundHttpException();
        }

        $subsection = $em->getRepository(Subsection::class)->findOneBy([
            'section' => $section,
            'slug' => $subsectionSlug,
        ]);
        if (!$subsection) {
            throw new NotFoundHttpException();
        }

        $article = $em->getRepository(Article::class)->findOneBy([
            'subsection' => $subsection,
            'slug' => $articleSlug,
            'isPublished' => true,
        ]);
        if (!$article) {
            throw new NotFoundHttpException();
        }

        return $this->renderArticle($article, $section, $subsection, $em, $renderer);
    }

    #[Route('/{sectionSlug}/article/{articleSlug}', name: 'front_article_section', priority: 20)]
    public function showFromSection(
        string $sectionSlug,
        string $articleSlug,
        EntityManagerInterface $em,
        ArticleRenderer $renderer,
    ): Response {
        $section = $em->getRepository(Section::class)->findOneBy(['slug' => $sectionSlug]);
        if (!$section) {
            throw new NotFoundHttpException();
        }

        $article = $em->getRepository(Article::class)->findOneBy([
            'section' => $section,
            'subsection' => null,
            'slug' => $articleSlug,
            'isPublished' => true,
        ]);
        if (!$article) {
            throw new NotFoundHttpException();
        }

        return $this->renderArticle($article, $section, null, $em, $renderer);
    }

    #[Route('/feedback/{id}', name: 'front_article_feedback', methods: ['POST'])]
    public function feedback(
        Article $article,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $feedback = new ArticleFeedback();
        $feedback->setArticle($article);
        $feedback->setIsHelpful((bool) ($data['is_helpful'] ?? true));
        $feedback->setComment($data['comment'] ?? null);
        $feedback->setIpAddress($request->getClientIp() ?? '0.0.0.0');

        $em->persist($feedback);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    private function renderArticle(
        Article $article,
        Section $section,
        ?Subsection $subsection,
        EntityManagerInterface $em,
        ArticleRenderer $renderer,
    ): Response {
        $htmlContent = $renderer->renderBlocks($article->getContent());
        $plainText = $renderer->extractText($article->getContent());
        $headings = $renderer->extractHeadings($article->getContent());
        $readingTimeMinutes = $this->estimateReadingTimeMinutes($plainText);
        [$freshnessLabel, $freshnessTone] = $this->buildFreshness($article);

        $feedbackRepo = $em->getRepository(ArticleFeedback::class);
        $helpfulCount = $feedbackRepo->count(['article' => $article, 'isHelpful' => true]);
        $notHelpfulCount = $feedbackRepo->count(['article' => $article, 'isHelpful' => false]);

        $siblingsCriteria = ['section' => $section, 'isPublished' => true];
        if ($subsection) {
            $siblingsCriteria['subsection'] = $subsection;
        } else {
            $siblingsCriteria['subsection'] = null;
        }

        $siblings = $em->getRepository(Article::class)->findBy(
            $siblingsCriteria,
            ['position' => 'ASC', 'id' => 'ASC']
        );

        $currentIndex = null;
        foreach ($siblings as $index => $sibling) {
            if ($sibling->getId() === $article->getId()) {
                $currentIndex = $index;
                break;
            }
        }

        $previousArticle = ($currentIndex !== null && $currentIndex > 0) ? $siblings[$currentIndex - 1] : null;
        $nextArticle = ($currentIndex !== null && $currentIndex < count($siblings) - 1) ? $siblings[$currentIndex + 1] : null;

        $relatedArticles = $this->resolveRelatedArticles($article, $em);
        if ($relatedArticles === []) {
            $relatedArticles = array_values(array_filter(
                $siblings,
                static fn (Article $sibling): bool => $sibling->getId() !== $article->getId()
            ));
            $relatedArticles = array_slice($relatedArticles, 0, 3);
        }

        return $this->render('front/article.html.twig', [
            'section' => $section,
            'subsection' => $subsection,
            'article' => $article,
            'htmlContent' => $htmlContent,
            'headings' => $headings,
            'readingTimeMinutes' => $readingTimeMinutes,
            'freshnessLabel' => $freshnessLabel,
            'freshnessTone' => $freshnessTone,
            'helpfulCount' => $helpfulCount,
            'notHelpfulCount' => $notHelpfulCount,
            'siblings' => $siblings,
            'previousArticle' => $previousArticle,
            'nextArticle' => $nextArticle,
            'relatedArticles' => $relatedArticles,
        ]);
    }

    private function estimateReadingTimeMinutes(string $text, int $wordsPerMinute = 220): int
    {
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
        if ($clean === '') {
            return 1;
        }

        $words = preg_split('/\s+/', $clean);
        $wordCount = is_array($words) ? count($words) : 0;

        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }

    /**
     * @return list<Article>
     */
    private function resolveRelatedArticles(Article $article, EntityManagerInterface $em): array
    {
        $content = $article->getContent() ?? [];
        $relatedHints = $content['related'] ?? [];
        if (!is_array($relatedHints) || $relatedHints === []) {
            return [];
        }

        $titles = [];
        $slugs = [];
        foreach ($relatedHints as $hint) {
            if (!is_string($hint)) {
                continue;
            }
            $clean = trim($hint);
            if ($clean === '') {
                continue;
            }
            $titles[] = $clean;
            $slug = $this->simpleSlug($clean);
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        $titles = array_values(array_unique($titles));
        $slugs = array_values(array_unique($slugs));
        if ($titles === [] && $slugs === []) {
            return [];
        }

        $qb = $em->getRepository(Article::class)->createQueryBuilder('a')
            ->where('a.isPublished = :published')
            ->andWhere('a.id != :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $article->getId());

        if ($titles !== [] && $slugs !== []) {
            $qb->andWhere('(a.title IN (:titles) OR a.slug IN (:slugs))')
                ->setParameter('titles', $titles)
                ->setParameter('slugs', $slugs);
        } elseif ($titles !== []) {
            $qb->andWhere('a.title IN (:titles)')
                ->setParameter('titles', $titles);
        } else {
            $qb->andWhere('a.slug IN (:slugs)')
                ->setParameter('slugs', $slugs);
        }

        /** @var list<Article> $matches */
        $matches = $qb->orderBy('a.position', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        if ($matches === []) {
            return [];
        }

        $bySlug = [];
        $byTitle = [];
        foreach ($matches as $match) {
            $matchSlug = $match->getSlug();
            if (is_string($matchSlug) && $matchSlug !== '') {
                $bySlug[$matchSlug] = $match;
            }
            $matchTitle = $match->getTitle();
            if (is_string($matchTitle) && $matchTitle !== '') {
                $byTitle[mb_strtolower($matchTitle)] = $match;
            }
        }

        $ordered = [];
        $seenIds = [];
        foreach ($relatedHints as $hint) {
            if (!is_string($hint)) {
                continue;
            }

            $candidate = null;
            $slug = $this->simpleSlug($hint);
            if ($slug !== '' && isset($bySlug[$slug])) {
                $candidate = $bySlug[$slug];
            } else {
                $normalizedTitle = mb_strtolower(trim($hint));
                if ($normalizedTitle !== '' && isset($byTitle[$normalizedTitle])) {
                    $candidate = $byTitle[$normalizedTitle];
                }
            }

            if (!$candidate) {
                continue;
            }

            $id = $candidate->getId();
            if ($id === null || isset($seenIds[$id])) {
                continue;
            }

            $ordered[] = $candidate;
            $seenIds[$id] = true;
        }

        return array_slice($ordered, 0, 3);
    }

    private function simpleSlug(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        $ascii = is_string($ascii) ? $ascii : $normalized;
        $ascii = strtolower($ascii);
        $ascii = preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? '';

        return trim($ascii, '-');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function buildFreshness(Article $article): array
    {
        $updatedAt = $article->getUpdatedAt();
        if (!$updatedAt) {
            return ['Date inconnue', 'warning'];
        }

        $days = (int) $updatedAt->diff(new \DateTimeImmutable())->format('%a');
        if ($days <= 30) {
            return ['À jour', 'fresh'];
        }
        if ($days <= 90) {
            return ['À vérifier', 'warning'];
        }

        return ['Possiblement obsolète', 'stale'];
    }
}
