<?php

namespace App\Repository;

use App\Entity\Feature;
use App\Entity\FeatureTranslation;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FeatureTranslation>
 */
class FeatureTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeatureTranslation::class);
    }

    /**
     * Find translation by feature and language
     */
    public function findByFeatureAndLanguage(Feature $feature, Language $language): ?FeatureTranslation
    {
        return $this->findOneBy([
            'feature' => $feature,
            'language' => $language
        ]);
    }

    /**
     * Find translations by language code
     */
    public function findByLanguageCode(string $languageCode): array
    {
        return $this->createQueryBuilder('ft')
            ->join('ft.language', 'l')
            ->where('l.code = :code')
            ->setParameter('code', $languageCode)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get translation statistics for a language
     */
    public function getTranslationStatistics(string $languageCode): array
    {
        $total = $this->createQueryBuilder('ft')
            ->select('COUNT(ft.id)')
            ->join('ft.language', 'l')
            ->where('l.code = :code')
            ->setParameter('code', $languageCode)
            ->getQuery()
            ->getSingleScalarResult();

        $complete = $this->createQueryBuilder('ft')
            ->select('COUNT(ft.id)')
            ->join('ft.language', 'l')
            ->where('l.code = :code')
            ->andWhere('ft.title IS NOT NULL')
            ->andWhere('ft.title != :empty')
            ->andWhere('ft.description IS NOT NULL')
            ->andWhere('ft.description != :empty')
            ->setParameter('code', $languageCode)
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();

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
     * Duplicate translation to another language
     */
    public function duplicateTranslation(FeatureTranslation $sourceTranslation, Language $targetLanguage): FeatureTranslation
    {
        $newTranslation = new FeatureTranslation();
        $newTranslation->setFeature($sourceTranslation->getFeature());
        $newTranslation->setLanguage($targetLanguage);
        $newTranslation->setTitle($sourceTranslation->getTitle());
        $newTranslation->setDescription($sourceTranslation->getDescription());
        $newTranslation->setMetaTitle($sourceTranslation->getMetaTitle());
        $newTranslation->setMetaDescription($sourceTranslation->getMetaDescription());

        return $newTranslation;
    }

    /**
     * Find incomplete translations
     */
    public function findIncompleteTranslations(string $languageCode = null): array
    {
        $qb = $this->createQueryBuilder('ft')
            ->join('ft.language', 'l')
            ->where('ft.title IS NULL OR ft.title = :empty')
            ->orWhere('ft.description IS NULL OR ft.description = :empty')
            ->setParameter('empty', '');

        if ($languageCode) {
            $qb->andWhere('l.code = :code')
               ->setParameter('code', $languageCode);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all translations for a feature
     */
    public function findByFeature(Feature $feature): array
    {
        return $this->createQueryBuilder('ft')
            ->join('ft.language', 'l')
            ->where('ft.feature = :feature')
            ->setParameter('feature', $feature)
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}