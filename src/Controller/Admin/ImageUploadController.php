<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ImageUploadController extends AbstractController
{
    #[Route('/admin/upload/image', name: 'admin_upload_image', methods: ['POST'])]
    public function upload(Request $request, SluggerInterface $slugger): JsonResponse
    {
        $file = $request->files->get('image');

        if (!$file) {
            return new JsonResponse(['success' => 0, 'message' => 'Aucun fichier envoyé.'], 400);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return new JsonResponse(['success' => 0, 'message' => 'Type de fichier non autorisé.'], 400);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse(['success' => 0, 'message' => 'Fichier trop volumineux (max 5 Mo).'], 400);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename)->lower();
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
        $file->move($uploadDir, $newFilename);

        return new JsonResponse([
            'success' => 1,
            'file' => [
                'url' => '/uploads/' . $newFilename,
            ],
        ]);
    }
}
