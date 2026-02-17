<?php

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\ArticleFeedback;
use App\Entity\Section;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        $sectionCount = $em->getRepository(Section::class)->count([]);
        $articleCount = $em->getRepository(Article::class)->count([]);
        $publishedCount = $em->getRepository(Article::class)->count(['isPublished' => true]);
        $feedbackCount = $em->getRepository(ArticleFeedback::class)->count([]);

        $recentArticles = $em->getRepository(Article::class)->findBy(
            [],
            ['updatedAt' => 'DESC'],
            5
        );

        return $this->render('admin/dashboard.html.twig', [
            'sectionCount' => $sectionCount,
            'articleCount' => $articleCount,
            'publishedCount' => $publishedCount,
            'feedbackCount' => $feedbackCount,
            'recentArticles' => $recentArticles,
        ]);
    }
}
