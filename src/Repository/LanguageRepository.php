<?php

namespace App\Repository;

use App\Entity\Language;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Language>
 */
class LanguageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Language::class);
    }

    public function findActiveLanguages(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('l.sortOrder', 'ASC')
            ->addOrderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDefaultLanguage(): ?Language
    {
        return $this->createQueryBuilder('l')
            ->where('l.isDefault = :default')
            ->andWhere('l.isActive = :active')
            ->setParameter('default', true)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCode(string $code): ?Language
    {
        return $this->createQueryBuilder('l')
            ->where('l.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveByCode(string $code): ?Language
    {
        return $this->createQueryBuilder('l')
            ->where('l.code = :code')
            ->andWhere('l.isActive = :active')
            ->setParameter('code', $code)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function setAsDefault(Language $language): void
    {
        // First, remove default from all languages
        $this->createQueryBuilder('l')
            ->update()
            ->set('l.isDefault', ':false')
            ->setParameter('false', false)
            ->getQuery()
            ->execute();

        // Then set the new default
        $language->setIsDefault(true);
        $this->getEntityManager()->flush();
    }

    public function getAllOrderedBySortOrder(): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.sortOrder', 'ASC')
            ->addOrderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveLanguageCodes(): array
    {
        $languages = $this->createQueryBuilder('l')
            ->select('l.code')
            ->where('l.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('l.sortOrder', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($languages, 'code');
    }
}
