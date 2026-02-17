<?php

namespace App\Controller\Front;

use App\Entity\Section;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'front_home')]
    public function index(EntityManagerInterface $em): Response
    {
        $sections = $em->getRepository(Section::class)->findBy([], ['position' => 'ASC']);

        return $this->render('front/home.html.twig', [
            'sections' => $sections,
        ]);
    }
}
