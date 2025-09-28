<?php

namespace App\Repository;

use App\Entity\FAQ;
use App\Entity\FAQTranslation;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FAQTranslation>
 */
class FAQTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FAQTranslation::class);
    }

    /**
     * Find translation by FAQ and language
     */
    public function findByFAQAndLanguage(FAQ $faq, Language $language): ?FAQTranslation
    {
        return $this->findOneBy([
            'faq' => $faq,
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
            ->andWhere('ft.question IS NOT NULL')
            ->andWhere('ft.question != :empty')
            ->andWhere('ft.answer IS NOT NULL')
            ->andWhere('ft.answer != :empty')
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
    public function duplicateTranslation(FAQTranslation $sourceTranslation, Language $targetLanguage): FAQTranslation
    {
        $newTranslation = new FAQTranslation();
        $newTranslation->setFaq($sourceTranslation->getFaq());
        $newTranslation->setLanguage($targetLanguage);
        $newTranslation->setQuestion($sourceTranslation->getQuestion());
        $newTranslation->setAnswer($sourceTranslation->getAnswer());

        return $newTranslation;
    }

    /**
     * Find incomplete translations
     */
    public function findIncompleteTranslations(string $languageCode = null): array
    {
        $qb = $this->createQueryBuilder('ft')
            ->join('ft.language', 'l')
            ->where('ft.question IS NULL OR ft.question = :empty')
            ->orWhere('ft.answer IS NULL OR ft.answer = :empty')
            ->setParameter('empty', '');

        if ($languageCode) {
            $qb->andWhere('l.code = :code')
               ->setParameter('code', $languageCode);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all translations for a FAQ
     */
    public function findByFAQ(FAQ $faq): array
    {
        return $this->createQueryBuilder('ft')
            ->join('ft.language', 'l')
            ->where('ft.faq = :faq')
            ->setParameter('faq', $faq)
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search in FAQ translations
     */
    public function searchInTranslations(string $query, string $languageCode = null): array
    {
        $qb = $this->createQueryBuilder('ft')
            ->join('ft.language', 'l')
            ->join('ft.faq', 'f')
            ->where('f.isActive = :active')
            ->andWhere('ft.question LIKE :query OR ft.answer LIKE :query')
            ->setParameter('active', true)
            ->setParameter('query', '%' . $query . '%');

        if ($languageCode) {
            $qb->andWhere('l.code = :code')
               ->setParameter('code', $languageCode);
        }

        return $qb->getQuery()->getResult();
    }
}