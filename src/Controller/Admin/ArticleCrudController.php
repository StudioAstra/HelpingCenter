<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\ArticleVersion;
use App\Entity\Section;
use App\Entity\Subsection;
use App\Service\ArticleRenderer;
use App\Service\ArticleVersionService;
use App\Service\HelpZipImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

    #[Route('/import', name: 'admin_article_import', methods: ['GET', 'POST'])]
    public function import(Request $request, HelpZipImporter $importer): Response
    {
        if ($request->isMethod('POST')) {
            /** @var UploadedFile|null $zipFile */
            $zipFile = $request->files->get('zip_file');
            if (!$zipFile) {
                $this->addFlash('error', 'Aucun fichier ZIP envoyé.');
                return $this->redirectToRoute('admin_article_import');
            }

            $extension = strtolower((string) $zipFile->getClientOriginalExtension());
            if ($extension !== 'zip') {
                $this->addFlash('error', 'Le fichier doit être un ZIP.');
                return $this->redirectToRoute('admin_article_import');
            }

            $tmpDir = sys_get_temp_dir() . '/help-import';
            if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
                $this->addFlash('error', "Impossible de préparer le dossier temporaire d'import.");
                return $this->redirectToRoute('admin_article_import');
            }

            $tmpFilename = 'import-' . uniqid('', true) . '.zip';
            $tmpPath = $tmpDir . '/' . $tmpFilename;

            try {
                $zipFile->move($tmpDir, $tmpFilename);
                $stats = $importer->importFromZip($tmpPath);

                $this->addFlash(
                    'success',
                    sprintf(
                        'Import terminé. Catégories créées: %d, sous-catégories créées: %d, articles créés: %d, articles mis à jour: %d.',
                        $stats['created_sections'],
                        $stats['created_subsections'],
                        $stats['created_articles'],
                        $stats['updated_articles']
                    )
                );

                @unlink($tmpPath);
                return $this->redirectToRoute('admin_article_index');
            } catch (\RuntimeException $e) {
                @unlink($tmpPath);
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('admin_article_import');
            } catch (\Throwable $e) {
                @unlink($tmpPath);
                $this->addFlash('error', "Erreur inattendue pendant l'import. " . $e->getMessage());
                return $this->redirectToRoute('admin_article_import');
            }
        }

        return $this->render('admin/article/import.html.twig');
    }

    #[Route('/new', name: 'admin_article_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        ArticleRenderer $renderer,
        ArticleVersionService $versionService,
    ): Response {
        $sections = $em->getRepository(Section::class)->findBy([], ['position' => 'ASC']);
        $subsections = $em->getRepository(Subsection::class)->findBy([], ['section' => 'ASC', 'position' => 'ASC']);

        if ($request->isMethod('POST')) {
            [$section, $subsection] = $this->resolveLocation((string) $request->request->get('location_id', ''), $em);
            if (!$section) {
                throw $this->createNotFoundException('Section introuvable.');
            }
            $content = $this->parseSubmittedContent($request, ['markdown' => '']);

            $article = new Article();
            $article->setSection($section);
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
            'sections' => $sections,
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
        $sections = $em->getRepository(Section::class)->findBy([], ['position' => 'ASC']);
        $subsections = $em->getRepository(Subsection::class)->findBy([], ['section' => 'ASC', 'position' => 'ASC']);

        if ($request->isMethod('POST')) {
            [$section, $subsection] = $this->resolveLocation((string) $request->request->get('location_id', ''), $em);
            if (!$section) {
                throw $this->createNotFoundException('Section introuvable.');
            }
            $content = $this->parseSubmittedContent($request, $article->getContent() ?? ['markdown' => '']);

            $article->setSection($section);
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
            'sections' => $sections,
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

    /**
     * @return array{0: ?Section, 1: ?Subsection}
     */
    private function resolveLocation(string $locationId, EntityManagerInterface $em): array
    {
        if (str_starts_with($locationId, 'section:')) {
            $sectionId = (int) substr($locationId, strlen('section:'));
            $section = $em->getRepository(Section::class)->find($sectionId);

            return [$section, null];
        }

        if (str_starts_with($locationId, 'subsection:')) {
            $subsectionId = (int) substr($locationId, strlen('subsection:'));
            $subsection = $em->getRepository(Subsection::class)->find($subsectionId);

            return [$subsection?->getSection(), $subsection];
        }

        return [null, null];
    }

    private function parseSubmittedContent(Request $request, array $fallback): array
    {
        $raw = $request->request->get('content');
        if (!is_string($raw) || trim($raw) === '') {
            return $fallback;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return $fallback;
        }

        return $decoded;
    }
}
