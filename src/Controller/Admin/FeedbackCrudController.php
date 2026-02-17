<?php

namespace App\Controller\Admin;

use App\Entity\ArticleFeedback;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/feedbacks')]
class FeedbackCrudController extends AbstractController
{
    #[Route('', name: 'admin_feedback_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $feedbacks = $em->getRepository(ArticleFeedback::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/feedback/index.html.twig', [
            'feedbacks' => $feedbacks,
        ]);
    }
}

