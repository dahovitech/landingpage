<?php

namespace App\Repository;

use App\Entity\PricingPlan;
use App\Entity\PricingPlanTranslation;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PricingPlanTranslation>
 */
class PricingPlanTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PricingPlanTranslation::class);
    }

    /**
     * Find translation by pricing plan and language
     */
    public function findByPricingPlanAndLanguage(PricingPlan $pricingPlan, Language $language): ?PricingPlanTranslation
    {
        return $this->findOneBy([
            'pricingPlan' => $pricingPlan,
            'language' => $language
        ]);
    }

    /**
     * Find translations by language code
     */
    public function findByLanguageCode(string $languageCode): array
    {
        return $this->createQueryBuilder('pt')
            ->join('pt.language', 'l')
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
        $total = $this->createQueryBuilder('pt')
            ->select('COUNT(pt.id)')
            ->join('pt.language', 'l')
            ->where('l.code = :code')
            ->setParameter('code', $languageCode)
            ->getQuery()
            ->getSingleScalarResult();

        $complete = $this->createQueryBuilder('pt')
            ->select('COUNT(pt.id)')
            ->join('pt.language', 'l')
            ->where('l.code = :code')
            ->andWhere('pt.name IS NOT NULL')
            ->andWhere('pt.name != :empty')
            ->andWhere('pt.description IS NOT NULL')
            ->andWhere('pt.description != :empty')
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
    public function duplicateTranslation(PricingPlanTranslation $sourceTranslation, Language $targetLanguage): PricingPlanTranslation
    {
        $newTranslation = new PricingPlanTranslation();
        $newTranslation->setPricingPlan($sourceTranslation->getPricingPlan());
        $newTranslation->setLanguage($targetLanguage);
        $newTranslation->setName($sourceTranslation->getName());
        $newTranslation->setDescription($sourceTranslation->getDescription());
        $newTranslation->setFeatures($sourceTranslation->getFeatures());
        $newTranslation->setCtaText($sourceTranslation->getCtaText());

        return $newTranslation;
    }

    /**
     * Find incomplete translations
     */
    public function findIncompleteTranslations(string $languageCode = null): array
    {
        $qb = $this->createQueryBuilder('pt')
            ->join('pt.language', 'l')
            ->where('pt.name IS NULL OR pt.name = :empty')
            ->orWhere('pt.description IS NULL OR pt.description = :empty')
            ->setParameter('empty', '');

        if ($languageCode) {
            $qb->andWhere('l.code = :code')
               ->setParameter('code', $languageCode);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all translations for a pricing plan
     */
    public function findByPricingPlan(PricingPlan $pricingPlan): array
    {
        return $this->createQueryBuilder('pt')
            ->join('pt.language', 'l')
            ->where('pt.pricingPlan = :pricingPlan')
            ->setParameter('pricingPlan', $pricingPlan)
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}