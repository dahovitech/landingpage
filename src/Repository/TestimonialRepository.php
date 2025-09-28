<?php

namespace App\Repository;

use App\Entity\Testimonial;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Testimonial>
 */
class TestimonialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Testimonial::class);
    }

    /**
     * Find all active testimonials ordered by sort order
     */
    public function findActiveTestimonials(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find featured testimonials only
     */
    public function findFeaturedTestimonials(int $limit = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.isActive = :active')
            ->andWhere('t.isFeatured = :featured')
            ->setParameter('active', true)
            ->setParameter('featured', true)
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find testimonials by rating
     */
    public function findByRating(int $rating): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isActive = :active')
            ->andWhere('t.rating = :rating')
            ->setParameter('active', true)
            ->setParameter('rating', $rating)
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find testimonials with minimum rating
     */
    public function findWithMinimumRating(int $minRating = 4): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isActive = :active')
            ->andWhere('t.rating >= :rating')
            ->setParameter('active', true)
            ->setParameter('rating', $minRating)
            ->orderBy('t.rating', 'DESC')
            ->addOrderBy('t.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find testimonials with translations count
     */
    public function findWithTranslationsCount(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.translations', 'tr')
            ->groupBy('t.id')
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next sort order
     */
    public function getNextSortOrder(): int
    {
        $maxSortOrder = $this->createQueryBuilder('t')
            ->select('MAX(t.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxSortOrder ?? 0) + 1;
    }

    /**
     * Count total testimonials
     */
    public function countTotal(): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active testimonials
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get average rating
     */
    public function getAverageRating(): float
    {
        $result = $this->createQueryBuilder('t')
            ->select('AVG(t.rating)')
            ->where('t.isActive = :active')
            ->andWhere('t.rating IS NOT NULL')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return round($result ?? 0, 1);
    }
}