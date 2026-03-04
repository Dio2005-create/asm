<?php

namespace App\Repository;

use App\Entity\ReleveBf;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReleveBf>
 *
 * @method ReleveBf|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReleveBf|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReleveBf[]    findAll()
 * @method ReleveBf[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReleveBfRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReleveBf::class);
    }

    public function findUniqueYears()
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        return $this->createQueryBuilder('r')
            ->select('DISTINCT YEAR(r.dateReleve) AS year')
            ->orderBy('year', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function findByYearAndMonth($year,$month)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
        $emConfig->addCustomDatetimeFunction('DAY', 'DoctrineExtensions\Query\Mysql\Day');
        return $this->createQueryBuilder('r')
            ->where('YEAR(r.dateReleve) = :year')
            ->andWhere('MONTH(r.dateReleve) = :month')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->getQuery()
            ->getResult();
    }

    public function add(ReleveBf $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ReleveBf $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return ReleveBf[] Returns an array of ReleveBf objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ReleveBf
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
