<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class ImageUploadController extends AbstractController
{
    #[Route('/admin/upload/image', name: 'admin_upload_image', methods: ['POST'])]
    public function upload(Request $request, SluggerInterface $slugger): JsonResponse
    {
        try {
            $file = $request->files->get('image');

            if (!$file) {
                return new JsonResponse(['success' => 0, 'message' => 'Aucun fichier envoyé.'], 400);
            }

            $clientMimeType = strtolower((string) $file->getClientMimeType());
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            if (!in_array($clientMimeType, $allowedMimeTypes, true)) {
                return new JsonResponse(['success' => 0, 'message' => 'Type de fichier non autorisé.'], 400);
            }

            if ($file->getSize() > 5 * 1024 * 1024) {
                return new JsonResponse(['success' => 0, 'message' => 'Fichier trop volumineux (max 5 Mo).'], 400);
            }

            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = (string) $slugger->slug($originalFilename)->lower();
            if ($safeFilename === '') {
                $safeFilename = 'image';
            }

            $clientExtension = strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            $extension = in_array($clientExtension, $allowedExtensions, true) ? $clientExtension : 'bin';
            $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $extension;

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
            if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                return new JsonResponse(['success' => 0, 'message' => 'Impossible de créer le dossier uploads.'], 500);
            }

            if (!is_writable($uploadDir)) {
                @chmod($uploadDir, 0777);
            }

            if (!is_writable($uploadDir)) {
                return new JsonResponse(['success' => 0, 'message' => "Le dossier d'upload n'est pas accessible en écriture."], 500);
            }

            $file->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            return new JsonResponse(['success' => 0, 'message' => "Echec de l'envoi du fichier."], 500);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => 0, 'message' => "Erreur serveur pendant l'envoi: " . $e->getMessage()], 500);
        }

        return new JsonResponse([
            'success' => 1,
            'file' => [
                'url' => '/uploads/' . $newFilename,
            ],
        ]);
    }
}
