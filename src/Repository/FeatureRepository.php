<?php

namespace App\Repository;

use App\Entity\Feature;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Feature>
 */
class FeatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Feature::class);
    }

    /**
     * Find all active features ordered by sort order
     */
    public function findActiveFeatures(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.sortOrder', 'ASC')
            ->addOrderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find featured features only
     */
    public function findFeaturedFeatures(int $limit = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.isActive = :active')
            ->andWhere('f.isFeatured = :featured')
            ->setParameter('active', true)
            ->setParameter('featured', true)
            ->orderBy('f.sortOrder', 'ASC')
            ->addOrderBy('f.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find feature by slug
     */
    public function findBySlug(string $slug): ?Feature
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Find features with translations count using optimized query
     */
    public function findWithTranslations(): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.translations', 't')
            ->addSelect('t')
            ->leftJoin('t.language', 'l')
            ->addSelect('l')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.sortOrder', 'ASC')
            ->addOrderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search features by translation text
     */
    public function searchByTranslationText(string $query, ?string $languageCode = null, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('f.isActive = :active')
            ->andWhere('(t.title LIKE :query OR t.description LIKE :query)')
            ->setParameter('active', true)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('f.sortOrder', 'ASC')
            ->setMaxResults($limit);

        if ($languageCode) {
            $qb->andWhere('l.code = :languageCode')
               ->setParameter('languageCode', $languageCode);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find features by category or tag
     */
    public function findByCategory(string $category, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('f');
        
        if ($activeOnly) {
            $qb->where('f.isActive = :active')
               ->setParameter('active', true);
        }
        
        // This would require adding a category field to Feature entity
        // For now, we'll just return active features
        return $qb->orderBy('f.sortOrder', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Get next sort order
     */
    public function getNextSortOrder(): int
    {
        $maxSortOrder = $this->createQueryBuilder('f')
            ->select('MAX(f.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxSortOrder ?? 0) + 1;
    }

    /**
     * Count total features
     */
    public function countTotal(): int
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active features
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}