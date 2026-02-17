<?php

namespace App\Controller\Admin;

use App\Entity\Section;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/sections')]
class SectionCrudController extends AbstractController
{
    #[Route('', name: 'admin_section_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $sections = $em->getRepository(Section::class)->findBy([], ['position' => 'ASC']);

        return $this->render('admin/section/index.html.twig', [
            'sections' => $sections,
        ]);
    }

    #[Route('/new', name: 'admin_section_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if ($request->isMethod('POST')) {
            $section = new Section();
            $section->setTitle($request->request->get('title'));
            $section->setSlug($slugger->slug($request->request->get('title'))->lower()->toString());
            $section->setDescription($request->request->get('description'));
            $section->setIcon($request->request->get('icon'));
            $section->setPosition((int) $request->request->get('position', 0));

            $em->persist($section);
            $em->flush();

            $this->addFlash('success', 'Section créée avec succès.');
            return $this->redirectToRoute('admin_section_index');
        }

        return $this->render('admin/section/form.html.twig', [
            'section' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_section_edit', methods: ['GET', 'POST'])]
    public function edit(Section $section, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if ($request->isMethod('POST')) {
            $section->setTitle($request->request->get('title'));
            $section->setSlug($slugger->slug($request->request->get('title'))->lower()->toString());
            $section->setDescription($request->request->get('description'));
            $section->setIcon($request->request->get('icon'));
            $section->setPosition((int) $request->request->get('position', 0));

            $em->flush();

            $this->addFlash('success', 'Section modifiée avec succès.');
            return $this->redirectToRoute('admin_section_index');
        }

        return $this->render('admin/section/form.html.twig', [
            'section' => $section,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_section_delete', methods: ['POST'])]
    public function delete(Section $section, EntityManagerInterface $em): Response
    {
        $em->remove($section);
        $em->flush();

        $this->addFlash('success', 'Section supprimée.');
        return $this->redirectToRoute('admin_section_index');
    }
}
