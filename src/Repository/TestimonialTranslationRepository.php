<?php

namespace App\Repository;

use App\Entity\Testimonial;
use App\Entity\TestimonialTranslation;
use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TestimonialTranslation>
 */
class TestimonialTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestimonialTranslation::class);
    }

    /**
     * Find translation by testimonial and language
     */
    public function findByTestimonialAndLanguage(Testimonial $testimonial, Language $language): ?TestimonialTranslation
    {
        return $this->findOneBy([
            'testimonial' => $testimonial,
            'language' => $language
        ]);
    }

    /**
     * Find translations by language code
     */
    public function findByLanguageCode(string $languageCode): array
    {
        return $this->createQueryBuilder('tt')
            ->join('tt.language', 'l')
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
        $total = $this->createQueryBuilder('tt')
            ->select('COUNT(tt.id)')
            ->join('tt.language', 'l')
            ->where('l.code = :code')
            ->setParameter('code', $languageCode)
            ->getQuery()
            ->getSingleScalarResult();

        $complete = $this->createQueryBuilder('tt')
            ->select('COUNT(tt.id)')
            ->join('tt.language', 'l')
            ->where('l.code = :code')
            ->andWhere('tt.content IS NOT NULL')
            ->andWhere('tt.content != :empty')
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
    public function duplicateTranslation(TestimonialTranslation $sourceTranslation, Language $targetLanguage): TestimonialTranslation
    {
        $newTranslation = new TestimonialTranslation();
        $newTranslation->setTestimonial($sourceTranslation->getTestimonial());
        $newTranslation->setLanguage($targetLanguage);
        $newTranslation->setContent($sourceTranslation->getContent());

        return $newTranslation;
    }

    /**
     * Find incomplete translations
     */
    public function findIncompleteTranslations(string $languageCode = null): array
    {
        $qb = $this->createQueryBuilder('tt')
            ->join('tt.language', 'l')
            ->where('tt.content IS NULL OR tt.content = :empty')
            ->setParameter('empty', '');

        if ($languageCode) {
            $qb->andWhere('l.code = :code')
               ->setParameter('code', $languageCode);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all translations for a testimonial
     */
    public function findByTestimonial(Testimonial $testimonial): array
    {
        return $this->createQueryBuilder('tt')
            ->join('tt.language', 'l')
            ->where('tt.testimonial = :testimonial')
            ->setParameter('testimonial', $testimonial)
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}