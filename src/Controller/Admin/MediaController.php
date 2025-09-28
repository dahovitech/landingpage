<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/media', name: 'admin_media_')]
class MediaController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(MediaRepository $mediaRepository): Response
    {
        $medias = $mediaRepository->findBy([], ['id' => 'DESC']);

        return $this->render('admin/media/index.html.twig', [
            'medias' => $medias,
        ]);
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // Vérifier si c'est un upload multiple
        $files = $request->files->get('files');
        if (is_array($files)) {
            return $this->multiUpload($request, $entityManager);
        }
        
        $uploadedFile = $request->files->get('file');
        
        if (!$uploadedFile instanceof UploadedFile) {
            return new JsonResponse(['success' => false, 'message' => 'Aucun fichier uploadé'], 400);
        }

        return $this->processFileUpload($uploadedFile, $entityManager);
    }

    #[Route('/multi-upload', name: 'multi_upload', methods: ['POST'])]
    public function multiUpload(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $uploadedFiles = $request->files->get('files');
        
        if (!is_array($uploadedFiles) || empty($uploadedFiles)) {
            return new JsonResponse(['success' => false, 'message' => 'Aucun fichier uploadé'], 400);
        }

        $results = [];
        $successCount = 0;
        $errors = [];

        foreach ($uploadedFiles as $index => $uploadedFile) {
            if (!$uploadedFile instanceof UploadedFile) {
                $errors[] = "Fichier #" . ($index + 1) . ": Fichier invalide";
                continue;
            }

            try {
                $result = $this->processFileUpload($uploadedFile, $entityManager, false);
                $data = json_decode($result->getContent(), true);
                
                if ($data['success']) {
                    $results[] = $data['media'];
                    $successCount++;
                } else {
                    $errors[] = "Fichier '{$uploadedFile->getClientOriginalName()}': " . $data['message'];
                }
            } catch (\Exception $e) {
                $errors[] = "Fichier '{$uploadedFile->getClientOriginalName()}': " . $e->getMessage();
            }
        }

        // Persister tous les changements en une fois
        $entityManager->flush();

        return new JsonResponse([
            'success' => $successCount > 0,
            'message' => $successCount > 0 ? 
                "$successCount fichier(s) uploadé(s) avec succès" . 
                (count($errors) > 0 ? " (" . count($errors) . " erreur(s))" : "") : 
                'Aucun fichier n\'a pu être uploadé',
            'results' => $results,
            'errors' => $errors,
            'successCount' => $successCount,
            'totalCount' => count($uploadedFiles)
        ]);
    }

    private function processFileUpload(UploadedFile $uploadedFile, EntityManagerInterface $entityManager, bool $flush = true): JsonResponse
    {
        // Validate file type
        $allowedMimeTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'image/svg+xml', 'application/pdf', 'video/mp4', 'video/webm',
            'audio/mpeg', 'audio/wav', 'audio/ogg'
        ];

        if (!in_array($uploadedFile->getMimeType(), $allowedMimeTypes)) {
            return new JsonResponse(['success' => false, 'message' => 'Type de fichier non autorisé'], 400);
        }

        // Check file size (max 10MB)
        if ($uploadedFile->getSize() > 10485760) {
            return new JsonResponse(['success' => false, 'message' => 'Fichier trop volumineux (max 10MB)'], 400);
        }

        try {
            $media = new Media();
            $media->setFile($uploadedFile);
            $media->setAlt($uploadedFile->getClientOriginalName());

            $entityManager->persist($media);
            
            if ($flush) {
                $entityManager->flush();
            }

            return new JsonResponse([
                'success' => true,
                'media' => [
                    'id' => $media->getId(),
                    'fileName' => $media->getFileName(),
                    'alt' => $media->getAlt(),
                    'extension' => $media->getExtension(),
                    'webPath' => $media->getWebPath(),
                    'url' => '/' . $media->getWebPath()
                ]
            ]);
        } catch (FileException $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/list', name: 'list', methods: ['GET'])]
    public function list(Request $request, MediaRepository $mediaRepository): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(10, $request->query->getInt('limit', 20)));
        $search = $request->query->get('search', '');
        $type = $request->query->get('type', '');

        $offset = ($page - 1) * $limit;

        $criteria = [];
        if ($search) {
            // Simple search by alt text or filename
            $medias = $mediaRepository->createQueryBuilder('m')
                ->where('m.alt LIKE :search OR m.fileName LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('m.id', 'DESC')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
                
            $total = $mediaRepository->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->where('m.alt LIKE :search OR m.fileName LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->getQuery()
                ->getSingleScalarResult();
        } else {
            $medias = $mediaRepository->findBy([], ['id' => 'DESC'], $limit, $offset);
            $total = $mediaRepository->count([]);
        }

        $mediasData = [];
        foreach ($medias as $media) {
            $mediasData[] = [
                'id' => $media->getId(),
                'fileName' => $media->getFileName(),
                'alt' => $media->getAlt(),
                'extension' => $media->getExtension(),
                'webPath' => $media->getWebPath(),
                'url' => '/' . $media->getWebPath(),
                'isImage' => in_array($media->getExtension(), ['jpg', 'jpeg', 'png', 'gif', 'webp'])
            ];
        }

        return new JsonResponse([
            'medias' => $mediasData,
            'pagination' => [
                'current' => $page,
                'total' => ceil($total / $limit),
                'count' => count($mediasData),
                'totalItems' => $total
            ]
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(Media $media, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $entityManager->remove($media);
            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Media supprimé avec succès']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur lors de la suppression'], 500);
        }
    }

    #[Route('/{id}/update', name: 'update', methods: ['PUT'])]
    public function update(Request $request, Media $media, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['alt'])) {
            $media->setAlt($data['alt']);
        }

        try {
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'media' => [
                    'id' => $media->getId(),
                    'fileName' => $media->getFileName(),
                    'alt' => $media->getAlt(),
                    'extension' => $media->getExtension(),
                    'webPath' => $media->getWebPath(),
                    'url' => '/' . $media->getWebPath()
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur lors de la mise à jour'], 500);
        }
    }
}
