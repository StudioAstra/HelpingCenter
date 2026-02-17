<?php

namespace App\Controller\Front;

use App\Entity\Section;
use App\Entity\Subsection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class SectionController extends AbstractController
{
    #[Route('/{sectionSlug}', name: 'front_section', priority: -10)]
    public function section(string $sectionSlug, EntityManagerInterface $em): Response
    {
        $section = $em->getRepository(Section::class)->findOneBy(['slug' => $sectionSlug]);

        if (!$section) {
            throw new NotFoundHttpException();
        }

        return $this->render('front/section.html.twig', [
            'section' => $section,
        ]);
    }

    #[Route('/{sectionSlug}/{subsectionSlug}', name: 'front_subsection', priority: -10)]
    public function subsection(
        string $sectionSlug,
        string $subsectionSlug,
        EntityManagerInterface $em,
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

        return $this->render('front/subsection.html.twig', [
            'section' => $section,
            'subsection' => $subsection,
        ]);
    }
}
