<?php

namespace App\Repository;

use App\Entity\PaymentTranche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentTranche>
 *
 * @method PaymentTranche|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaymentTranche|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaymentTranche[]    findAll()
 * @method PaymentTranche[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentTrancheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentTranche::class);
    }
	
	  public function findByYearAudit($year)
    {
        return $this->createQueryBuilder('pt')
            ->leftJoin('pt.contrat', 'c') // Jointure avec Contract (contrat)
            ->leftJoin('c.client', 'cl') // Jointure avec Client (client)
            ->where('YEAR(pt.date) = :year')
            ->andWhere('cl.audit = true') // Filtrer les clients avec audit à true
            ->setParameter('year', $year)
            ->orderBy('pt.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByYearMonthAudit($year, $month)
    {
        return $this->createQueryBuilder('pt')
            ->leftJoin('pt.contrat', 'c') // Jointure avec Contract (contrat)
            ->leftJoin('c.client', 'cl') // Jointure avec Client (client)
            ->where('YEAR(pt.date) = :year')
            ->andWhere('MONTH(pt.date) = :month')
            ->andWhere('cl.audit = true') // Filtrer les clients avec audit à true
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->orderBy('pt.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByYearMonthDayAudit($year, $month, $day)
    {
        return $this->createQueryBuilder('pt')
            ->leftJoin('pt.contrat', 'c') // Jointure avec Contract (contrat)
            ->leftJoin('c.client', 'cl') // Jointure avec Client (client)
            ->where('YEAR(pt.date) = :year')
            ->andWhere('MONTH(pt.date) = :month')
            ->andWhere('DAY(pt.date) = :day')
            ->andWhere('cl.audit = true') // Filtrer les clients avec audit à true
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->setParameter('day', $day)
            ->orderBy('pt.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
	
	 public function findByYear($year)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        return $this->createQueryBuilder('p')
            ->where('YEAR(p.date) = :year')
            ->setParameter('year', $year)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByYearMonth($year, $month)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
        return $this->createQueryBuilder('p')
            ->where('YEAR(p.date) = :year')
            ->andWhere('MONTH(p.date) = :month')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByYearMonthDay($year, $month, $day)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
        $emConfig->addCustomDatetimeFunction('DAY', 'DoctrineExtensions\Query\Mysql\Day');
        return $this->createQueryBuilder('p')
            ->where('YEAR(p.date) = :year')
            ->andWhere('MONTH(p.date) = :month')
            ->andWhere('DAY(p.date) = :day')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->setParameter('day', $day)
            ->orderBy('p.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function add(PaymentTranche $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PaymentTranche $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return PaymentTranche[] Returns an array of PaymentTranche objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?PaymentTranche
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
