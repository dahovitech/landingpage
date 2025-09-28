<?php

namespace App\Repository;

use App\Entity\FAQ;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FAQ>
 */
class FAQRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FAQ::class);
    }

    /**
     * Find all active FAQs ordered by sort order
     */
    public function findActiveFAQs(): array
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
     * Find featured FAQs only
     */
    public function findFeaturedFAQs(int $limit = null): array
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
     * Find FAQs by category
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.isActive = :active')
            ->andWhere('f.category = :category')
            ->setParameter('active', true)
            ->setParameter('category', $category)
            ->orderBy('f.sortOrder', 'ASC')
            ->addOrderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all categories
     */
    public function findAllCategories(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('DISTINCT f.category')
            ->where('f.isActive = :active')
            ->andWhere('f.category IS NOT NULL')
            ->setParameter('active', true)
            ->orderBy('f.category', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'category');
    }

    /**
     * Find FAQs grouped by category
     */
    public function findGroupedByCategory(): array
    {
        $faqs = $this->findActiveFAQs();
        $grouped = [];
        
        foreach ($faqs as $faq) {
            $category = $faq->getCategory() ?? 'General';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $faq;
        }
        
        return $grouped;
    }

    /**
     * Find FAQs with translations count
     */
    public function findWithTranslationsCount(): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.translations', 't')
            ->groupBy('f.id')
            ->orderBy('f.sortOrder', 'ASC')
            ->addOrderBy('f.createdAt', 'DESC')
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
     * Count total FAQs
     */
    public function countTotal(): int
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active FAQs
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

    /**
     * Count FAQs by category
     */
    public function countByCategory(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('f.category, COUNT(f.id) as count')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('f.category')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['category'] ?? 'General'] = $row['count'];
        }

        return $counts;
    }
}