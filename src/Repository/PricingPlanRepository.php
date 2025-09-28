<?php

namespace App\Repository;

use App\Entity\PricingPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PricingPlan>
 */
class PricingPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PricingPlan::class);
    }

    /**
     * Find all active pricing plans ordered by sort order
     */
    public function findActivePlans(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.price', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find popular plans only
     */
    public function findPopularPlans(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->andWhere('p.isPopular = :popular')
            ->setParameter('active', true)
            ->setParameter('popular', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find free plans
     */
    public function findFreePlans(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->andWhere('p.isFree = :free')
            ->setParameter('active', true)
            ->setParameter('free', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find plans by billing period
     */
    public function findByBillingPeriod(string $billingPeriod): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = :active')
            ->andWhere('p.billingPeriod = :period')
            ->setParameter('active', true)
            ->setParameter('period', $billingPeriod)
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.price', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find plan by slug
     */
    public function findBySlug(string $slug): ?PricingPlan
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Find plan by Stripe product ID
     */
    public function findByStripeProductId(string $stripeProductId): ?PricingPlan
    {
        return $this->findOneBy(['stripeProductId' => $stripeProductId]);
    }

    /**
     * Find plans with translations count
     */
    public function findWithTranslationsCount(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->groupBy('p.id')
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.price', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next sort order
     */
    public function getNextSortOrder(): int
    {
        $maxSortOrder = $this->createQueryBuilder('p')
            ->select('MAX(p.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxSortOrder ?? 0) + 1;
    }

    /**
     * Count total plans
     */
    public function countTotal(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active plans
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get price range
     */
    public function getPriceRange(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('MIN(p.price) as minPrice, MAX(p.price) as maxPrice')
            ->where('p.isActive = :active')
            ->andWhere('p.isFree = :free')
            ->setParameter('active', true)
            ->setParameter('free', false)
            ->getQuery()
            ->getSingleResult();

        return [
            'min' => $result['minPrice'] ?? '0.00',
            'max' => $result['maxPrice'] ?? '0.00'
        ];
    }
}