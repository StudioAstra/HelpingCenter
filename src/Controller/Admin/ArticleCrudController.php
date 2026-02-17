<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\ArticleVersion;
use App\Entity\Subsection;
use App\Service\ArticleRenderer;
use App\Service\ArticleVersionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/articles')]
class ArticleCrudController extends AbstractController
{
    #[Route('', name: 'admin_article_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $articles = $em->getRepository(Article::class)->findBy([], ['updatedAt' => 'DESC']);

        return $this->render('admin/article/index.html.twig', [
            'articles' => $articles,
        ]);
    }

    #[Route('/new', name: 'admin_article_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        ArticleRenderer $renderer,
        ArticleVersionService $versionService,
    ): Response {
        $subsections = $em->getRepository(Subsection::class)->findBy([], ['section' => 'ASC', 'position' => 'ASC']);

        if ($request->isMethod('POST')) {
            $subsection = $em->getRepository(Subsection::class)->find($request->request->get('subsection_id'));
            $content = json_decode($request->request->get('content', '{}'), true);

            $article = new Article();
            $article->setSubsection($subsection);
            $article->setTitle($request->request->get('title'));
            $article->setSlug($slugger->slug($request->request->get('title'))->lower()->toString());
            $article->setContent($content);
            $article->setSearchText($renderer->extractText($content));
            $article->setIsPublished($request->request->getBoolean('is_published'));
            $article->setPosition((int) $request->request->get('position', 0));

            if ($article->isPublished()) {
                $article->setPublishedAt(new \DateTimeImmutable());
            }

            $em->persist($article);
            $versionService->createVersion($article, $this->getUser()->getUserIdentifier());
            $em->flush();

            $this->addFlash('success', 'Article créé avec succès.');
            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/form.html.twig', [
            'article' => null,
            'subsections' => $subsections,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_article_edit', methods: ['GET', 'POST'])]
    public function edit(
        Article $article,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        ArticleRenderer $renderer,
        ArticleVersionService $versionService,
    ): Response {
        $subsections = $em->getRepository(Subsection::class)->findBy([], ['section' => 'ASC', 'position' => 'ASC']);

        if ($request->isMethod('POST')) {
            $subsection = $em->getRepository(Subsection::class)->find($request->request->get('subsection_id'));
            $content = json_decode($request->request->get('content', '{}'), true);

            $article->setSubsection($subsection);
            $article->setTitle($request->request->get('title'));
            $article->setSlug($slugger->slug($request->request->get('title'))->lower()->toString());
            $article->setContent($content);
            $article->setSearchText($renderer->extractText($content));
            $article->setIsPublished($request->request->getBoolean('is_published'));
            $article->setPosition((int) $request->request->get('position', 0));

            if ($article->isPublished() && !$article->getPublishedAt()) {
                $article->setPublishedAt(new \DateTimeImmutable());
            }

            $versionService->createVersion($article, $this->getUser()->getUserIdentifier());
            $em->flush();

            $this->addFlash('success', 'Article modifié avec succès.');
            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/form.html.twig', [
            'article' => $article,
            'subsections' => $subsections,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_article_delete', methods: ['POST'])]
    public function delete(Article $article, EntityManagerInterface $em): Response
    {
        $em->remove($article);
        $em->flush();

        $this->addFlash('success', 'Article supprimé.');
        return $this->redirectToRoute('admin_article_index');
    }

    #[Route('/{id}/versions', name: 'admin_article_versions')]
    public function versions(Article $article, EntityManagerInterface $em): Response
    {
        $versions = $em->getRepository(ArticleVersion::class)->findBy(
            ['article' => $article],
            ['versionNumber' => 'DESC']
        );

        return $this->render('admin/article/versions.html.twig', [
            'article' => $article,
            'versions' => $versions,
        ]);
    }

    #[Route('/{id}/versions/{versionId}/restore', name: 'admin_article_restore', methods: ['POST'])]
    public function restore(
        Article $article,
        int $versionId,
        EntityManagerInterface $em,
        ArticleRenderer $renderer,
        ArticleVersionService $versionService,
    ): Response {
        $version = $em->getRepository(ArticleVersion::class)->find($versionId);

        if (!$version || $version->getArticle()->getId() !== $article->getId()) {
            throw $this->createNotFoundException();
        }

        $article->setTitle($version->getTitle());
        $article->setContent($version->getContent());
        $article->setSearchText($renderer->extractText($version->getContent()));

        $versionService->createVersion($article, $this->getUser()->getUserIdentifier());
        $em->flush();

        $this->addFlash('success', 'Version restaurée avec succès.');
        return $this->redirectToRoute('admin_article_edit', ['id' => $article->getId()]);
    }
}
