<?php

namespace App\Controller\Admin;

use App\Entity\Section;
use App\Entity\Subsection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/subsections')]
class SubsectionCrudController extends AbstractController
{
    #[Route('', name: 'admin_subsection_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $subsections = $em->getRepository(Subsection::class)->findBy([], ['section' => 'ASC', 'position' => 'ASC']);

        return $this->render('admin/subsection/index.html.twig', [
            'subsections' => $subsections,
        ]);
    }

    #[Route('/new', name: 'admin_subsection_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $sections = $em->getRepository(Section::class)->findBy([], ['position' => 'ASC']);

        if ($request->isMethod('POST')) {
            $section = $em->getRepository(Section::class)->find($request->request->get('section_id'));

            $subsection = new Subsection();
            $subsection->setSection($section);
            $subsection->setTitle($request->request->get('title'));
            $subsection->setSlug($slugger->slug($request->request->get('title'))->lower()->toString());
            $subsection->setDescription($request->request->get('description'));
            $subsection->setIcon($request->request->get('icon'));
            $subsection->setPosition((int) $request->request->get('position', 0));

            $em->persist($subsection);
            $em->flush();

            $this->addFlash('success', 'Sous-section créée avec succès.');
            return $this->redirectToRoute('admin_subsection_index');
        }

        return $this->render('admin/subsection/form.html.twig', [
            'subsection' => null,
            'sections' => $sections,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_subsection_edit', methods: ['GET', 'POST'])]
    public function edit(Subsection $subsection, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $sections = $em->getRepository(Section::class)->findBy([], ['position' => 'ASC']);

        if ($request->isMethod('POST')) {
            $section = $em->getRepository(Section::class)->find($request->request->get('section_id'));

            $subsection->setSection($section);
            $subsection->setTitle($request->request->get('title'));
            $subsection->setSlug($slugger->slug($request->request->get('title'))->lower()->toString());
            $subsection->setDescription($request->request->get('description'));
            $subsection->setIcon($request->request->get('icon'));
            $subsection->setPosition((int) $request->request->get('position', 0));

            $em->flush();

            $this->addFlash('success', 'Sous-section modifiée avec succès.');
            return $this->redirectToRoute('admin_subsection_index');
        }

        return $this->render('admin/subsection/form.html.twig', [
            'subsection' => $subsection,
            'sections' => $sections,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_subsection_delete', methods: ['POST'])]
    public function delete(Subsection $subsection, EntityManagerInterface $em): Response
    {
        $em->remove($subsection);
        $em->flush();

        $this->addFlash('success', 'Sous-section supprimée.');
        return $this->redirectToRoute('admin_subsection_index');
    }
}
