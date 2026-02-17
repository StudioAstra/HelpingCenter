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
    #[Route('/{sectionSlug}/{subsectionSlug}/{articleSlug}', name: 'front_article', priority: -10)]
    public function show(
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

        $htmlContent = $renderer->renderBlocks($article->getContent());
        $headings = $renderer->extractHeadings($article->getContent());

        // Feedback stats
        $feedbackRepo = $em->getRepository(ArticleFeedback::class);
        $helpfulCount = $feedbackRepo->count(['article' => $article, 'isHelpful' => true]);
        $notHelpfulCount = $feedbackRepo->count(['article' => $article, 'isHelpful' => false]);

        // Sibling articles for navigation
        $siblings = $em->getRepository(Article::class)->findBy(
            ['subsection' => $subsection, 'isPublished' => true],
            ['position' => 'ASC']
        );

        return $this->render('front/article.html.twig', [
            'section' => $section,
            'subsection' => $subsection,
            'article' => $article,
            'htmlContent' => $htmlContent,
            'headings' => $headings,
            'helpfulCount' => $helpfulCount,
            'notHelpfulCount' => $notHelpfulCount,
            'siblings' => $siblings,
        ]);
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
}
