<?php

namespace App\Repository;

use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Find all active services ordered by sort order
     */
    public function findActiveServices(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find service by slug
     */
    public function findBySlug(string $slug): ?Service
    {
        return $this->createQueryBuilder('s')
            ->where('s.slug = :slug')
            ->andWhere('s.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find services with their translations for a specific language
     */
    public function findActiveServicesWithTranslations(string $languageCode): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.translations', 't')
            ->leftJoin('t.language', 'l')
            ->where('s.isActive = :active')
            ->andWhere('l.code = :languageCode OR l.code IS NULL')
            ->setParameter('active', true)
            ->setParameter('languageCode', $languageCode)
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find services that have translations in a specific language
     */
    public function findServicesWithTranslationInLanguage(string $languageCode): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.translations', 't')
            ->innerJoin('t.language', 'l')
            ->where('s.isActive = :active')
            ->andWhere('l.code = :languageCode')
            ->setParameter('active', true)
            ->setParameter('languageCode', $languageCode)
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count services with missing translations for a language
     */
    public function countServicesWithMissingTranslations(string $languageCode): int
    {
        $totalServices = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $servicesWithTranslation = $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.id)')
            ->innerJoin('s.translations', 't')
            ->innerJoin('t.language', 'l')
            ->where('s.isActive = :active')
            ->andWhere('l.code = :languageCode')
            ->setParameter('active', true)
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getSingleScalarResult();

        return $totalServices - $servicesWithTranslation;
    }

    /**
     * Get services statistics for admin dashboard
     */
    public function getServicesStatistics(): array
    {
        $qb = $this->createQueryBuilder('s');
        
        $total = (clone $qb)
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = (clone $qb)
            ->select('COUNT(s.id)')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $inactive = $total - $active;

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive
        ];
    }

    /**
     * Find services with incomplete translations
     */
    public function findServicesWithIncompleteTranslations(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.translations', 't')
            ->where('s.isActive = :active')
            ->andWhere('t.id IS NULL OR t.title = :empty OR t.description IS NULL')
            ->setParameter('active', true)
            ->setParameter('empty', '')
            ->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search services by title or description in any language
     */
    public function searchServices(string $query, ?string $languageCode = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.translations', 't')
            ->where('s.isActive = :active')
            ->andWhere('t.title LIKE :query OR t.description LIKE :query')
            ->setParameter('active', true)
            ->setParameter('query', '%' . $query . '%');

        if ($languageCode) {
            $qb->innerJoin('t.language', 'l')
                ->andWhere('l.code = :languageCode')
                ->setParameter('languageCode', $languageCode);
        }

        return $qb->orderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
