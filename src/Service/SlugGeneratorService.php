<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service for generating unique slugs across different entities
 */
class SlugGeneratorService
{
    private const MAX_ATTEMPTS = 100;

    public function __construct(
        private SluggerInterface $slugger,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Generate a unique slug for an entity
     */
    public function generateUniqueSlug(string $text, string $entityClass, ?int $excludeId = null): string
    {
        $baseSlug = $this->slugger->slug($text)->lower()->toString();
        
        // If text is empty, generate a random slug
        if (empty($baseSlug)) {
            $baseSlug = 'item-' . uniqid();
        }
        
        $slug = $baseSlug;
        $counter = 1;
        $attempts = 0;

        while ($this->slugExists($entityClass, $slug, $excludeId) && $attempts < self::MAX_ATTEMPTS) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            $attempts++;
        }

        if ($attempts >= self::MAX_ATTEMPTS) {
            // Fallback to timestamp-based slug
            $slug = $baseSlug . '-' . time() . '-' . rand(100, 999);
        }

        return $slug;
    }

    /**
     * Check if a slug already exists for the given entity class
     */
    private function slugExists(string $entityClass, string $slug, ?int $excludeId = null): bool
    {
        $repository = $this->entityManager->getRepository($entityClass);
        
        $qb = $repository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('e.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Generate slug from multiple text sources (fallback if one is empty)
     */
    public function generateSlugFromMultipleSources(array $textSources, string $entityClass, ?int $excludeId = null): string
    {
        foreach ($textSources as $text) {
            if (!empty(trim($text))) {
                return $this->generateUniqueSlug($text, $entityClass, $excludeId);
            }
        }

        // If all sources are empty, generate a random slug
        return $this->generateUniqueSlug('item-' . uniqid(), $entityClass, $excludeId);
    }

    /**
     * Update existing entities with missing slugs
     */
    public function generateMissingSlugs(string $entityClass, string $titleField = 'name'): int
    {
        $repository = $this->entityManager->getRepository($entityClass);
        
        // Find entities without slugs
        $entities = $repository->createQueryBuilder('e')
            ->where('e.slug IS NULL OR e.slug = :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        $updated = 0;
        foreach ($entities as $entity) {
            $titleValue = $this->getEntityFieldValue($entity, $titleField);
            $slug = $this->generateUniqueSlug($titleValue ?: 'item', $entityClass, $entity->getId());
            
            $entity->setSlug($slug);
            $this->entityManager->persist($entity);
            $updated++;
        }

        if ($updated > 0) {
            $this->entityManager->flush();
        }

        return $updated;
    }

    /**
     * Get field value from entity using getter method
     */
    private function getEntityFieldValue(object $entity, string $field): ?string
    {
        $getterMethod = 'get' . ucfirst($field);
        
        if (method_exists($entity, $getterMethod)) {
            return $entity->$getterMethod();
        }

        // Try direct property access
        if (property_exists($entity, $field)) {
            return $entity->$field;
        }

        return null;
    }

    /**
     * Validate slug format
     */
    public function isValidSlug(string $slug): bool
    {
        // A valid slug should:
        // - Be lowercase
        // - Contain only letters, numbers, and hyphens
        // - Not start or end with hyphen
        // - Not have consecutive hyphens
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }

    /**
     * Clean and format slug
     */
    public function cleanSlug(string $slug): string
    {
        // Remove invalid characters and format properly
        $slug = $this->slugger->slug($slug)->lower()->toString();
        
        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');
        
        // Replace multiple consecutive hyphens with single hyphen
        $slug = preg_replace('/-+/', '-', $slug);
        
        return $slug;
    }
}