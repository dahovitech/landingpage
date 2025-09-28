<?php

namespace App\Repository;

use App\Entity\ServiceTranslation;
use App\Entity\Language;
use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceTranslation>
 */
class ServiceTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceTranslation::class);
    }

    /**
     * Find translation by service and language
     */
    public function findByServiceAndLanguage(Service $service, Language $language): ?ServiceTranslation
    {
        return $this->createQueryBuilder('st')
            ->where('st.service = :service')
            ->andWhere('st.language = :language')
            ->setParameter('service', $service)
            ->setParameter('language', $language)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find translations by language code
     */
    public function findByLanguageCode(string $languageCode): array
    {
        return $this->createQueryBuilder('st')
            ->innerJoin('st.language', 'l')
            ->where('l.code = :languageCode')
            ->setParameter('languageCode', $languageCode)
            ->orderBy('st.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find incomplete translations (missing title or description)
     */
    public function findIncompleteTranslations(?string $languageCode = null): array
    {
        $qb = $this->createQueryBuilder('st')
            ->where('st.title = :empty OR st.description IS NULL OR st.description = :empty')
            ->setParameter('empty', '');

        if ($languageCode) {
            $qb->innerJoin('st.language', 'l')
                ->andWhere('l.code = :languageCode')
                ->setParameter('languageCode', $languageCode);
        }

        return $qb->orderBy('st.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count translations by language
     */
    public function countByLanguage(string $languageCode): int
    {
        return $this->createQueryBuilder('st')
            ->select('COUNT(st.id)')
            ->innerJoin('st.language', 'l')
            ->where('l.code = :languageCode')
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count complete translations by language
     */
    public function countCompleteByLanguage(string $languageCode): int
    {
        return $this->createQueryBuilder('st')
            ->select('COUNT(st.id)')
            ->innerJoin('st.language', 'l')
            ->where('l.code = :languageCode')
            ->andWhere('st.title != :empty')
            ->andWhere('st.description IS NOT NULL')
            ->andWhere('st.description != :empty')
            ->setParameter('languageCode', $languageCode)
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get translation statistics for a specific language
     */
    public function getTranslationStatistics(string $languageCode): array
    {
        $total = $this->countByLanguage($languageCode);
        $complete = $this->countCompleteByLanguage($languageCode);
        $incomplete = $total - $complete;
        $percentage = $total > 0 ? round(($complete / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'complete' => $complete,
            'incomplete' => $incomplete,
            'percentage' => $percentage
        ];
    }

    /**
     * Get translations that need attention (empty or very short)
     */
    public function findTranslationsNeedingAttention(?string $languageCode = null): array
    {
        $qb = $this->createQueryBuilder('st')
            ->where('st.title = :empty OR LENGTH(st.title) < :minLength')
            ->orWhere('st.description IS NULL OR st.description = :empty OR LENGTH(st.description) < :minDescLength')
            ->setParameter('empty', '')
            ->setParameter('minLength', 3)
            ->setParameter('minDescLength', 10);

        if ($languageCode) {
            $qb->innerJoin('st.language', 'l')
                ->andWhere('l.code = :languageCode')
                ->setParameter('languageCode', $languageCode);
        }

        return $qb->orderBy('st.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent translations (for dashboard)
     */
    public function findRecentTranslations(int $limit = 10): array
    {
        return $this->createQueryBuilder('st')
            ->orderBy('st.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Duplicate translation to another language (as base for translation)
     */
    public function duplicateTranslation(ServiceTranslation $sourceTranslation, Language $targetLanguage): ServiceTranslation
    {
        $newTranslation = new ServiceTranslation();
        $newTranslation->setService($sourceTranslation->getService());
        $newTranslation->setLanguage($targetLanguage);
        $newTranslation->setTitle($sourceTranslation->getTitle());
        $newTranslation->setDescription($sourceTranslation->getDescription());
        $newTranslation->setMetaTitle($sourceTranslation->getMetaTitle());
        $newTranslation->setMetaDescription($sourceTranslation->getMetaDescription());

        return $newTranslation;
    }
}
