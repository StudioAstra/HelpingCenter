<?php

namespace App\Controller\Front;

use App\Entity\Article;
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
        $articleRepo = $em->getRepository(Article::class);
        $articlesBySection = [];

        foreach ($sections as $section) {
            $articlesBySection[$section->getId()] = $articleRepo->createQueryBuilder('a')
                ->leftJoin('a.subsection', 'sub')
                ->andWhere('a.section = :section')
                ->andWhere('a.isPublished = :published')
                ->setParameter('section', $section)
                ->setParameter('published', true)
                ->orderBy('sub.position', 'ASC')
                ->addOrderBy('a.position', 'ASC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('front/home.html.twig', [
            'sections' => $sections,
            'articlesBySection' => $articlesBySection,
        ]);
    }
}
