<?php

namespace App\Repository;

use App\Entity\Releve;
use Doctrine\ORM\Query\Expr;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Releve>
 *
 * @method Releve|null find($id, $lockMode = null, $lockVersion = null)
 * @method Releve|null findOneBy(array $criteria, array $orderBy = null)
 * @method Releve[]    findAll()
 * @method Releve[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReleveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Releve::class);
    }
    public function findByYear($year)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
        $emConfig->addCustomDatetimeFunction('DAY', 'DoctrineExtensions\Query\Mysql\Day');
        return $this->createQueryBuilder('r')
            ->where('YEAR(r.dateReleve) = :year')
            ->setParameter('year', $year)
            ->getQuery()
            ->getResult();
    }
     public function findByMonthYear($year,$month)
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
    public function findByYearQuartierAndMonth($year, $quartierId, $month)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
        $emConfig->addCustomDatetimeFunction('DAY', 'DoctrineExtensions\Query\Mysql\Day');
        return $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c') // Join avec la table Client
            ->leftJoin('c.quartier', 'q') // Join avec la table Quartier à partir de la table Client
            ->where('YEAR(r.dateReleve) = :year')
            ->andWhere('q.id = :quartierId')
            ->andWhere('MONTH(r.dateReleve) = :month')
            ->setParameter('year', $year)
            ->setParameter('quartierId', $quartierId)
            ->setParameter('month', $month)
            ->getQuery()
            ->getResult();
    }

    public function findByYearReleve($year)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        return $this->createQueryBuilder('r')
            ->where('YEAR(r.factureDatePaiement) = :year')
			->andWhere('r.contract IS NULL')
            ->setParameter('year', $year)
            ->orderBy('r.factureDatePaiement', 'DESC')
            ->getQuery()
            ->getResult();
    }
	public function findByYearReleveAudit($year)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');

        return $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c') 
            ->where('c.audit = true') 
            ->andWhere('YEAR(r.factureDatePaiement) = :year')
			->andWhere('r.contract IS NULL')
            ->setParameter('year', $year)
            ->orderBy('r.factureDatePaiement', 'DESC')
            ->getQuery()
            ->getResult();
    }
	public function findByYearMonthReleveAudit($year, $month)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
        $emConfig->addCustomDatetimeFunction('DAY', 'DoctrineExtensions\Query\Mysql\Day');

        return $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')
            ->where('c.audit = true')
            ->andWhere('YEAR(r.factureDatePaiement) = :year')
            ->andWhere('MONTH(r.factureDatePaiement) = :month')
			->andWhere('r.contract IS NULL')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->orderBy('r.factureDatePaiement', 'DESC')
            ->getQuery()
            ->getResult();
    }
	
	  public function findByMonthYearAudit($year,$month)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
        $emConfig->addCustomDatetimeFunction('DAY', 'DoctrineExtensions\Query\Mysql\Day');
        return $this->createQueryBuilder('r')
			->leftJoin('r.client', 'c') 
            ->where('c.audit = true') 
            ->andWhere('YEAR(r.dateReleve) = :year')
            ->andWhere('MONTH(r.dateReleve) = :month')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->getQuery()
            ->getResult();
    }
    public function findByYearQuartierAndMonthAudit($year, $quartierId, $month)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
        $emConfig->addCustomDatetimeFunction('DAY', 'DoctrineExtensions\Query\Mysql\Day');
        return $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c') // Join avec la table Client
            ->leftJoin('c.quartier', 'q') // Join avec la table Quartier à partir de la table Client
			->where('c.audit = true')
            ->andWhere('YEAR(r.dateReleve) = :year')
            ->andWhere('q.id = :quartierId')
            ->andWhere('MONTH(r.dateReleve) = :month')
            ->setParameter('year', $year)
            ->setParameter('quartierId', $quartierId)
            ->setParameter('month', $month)
            ->getQuery()
            ->getResult();
    }
	public function findByYearMonthDayReleveAudit($year, $month, $day)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
        $emConfig->addCustomDatetimeFunction('DAY', 'DoctrineExtensions\Query\Mysql\Day');

        return $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c') // Joindre la table Client avec alias c
            ->where('c.audit = true') // Filtrer les clients avec audit à true
            ->andWhere('YEAR(r.factureDatePaiement) = :year')
            ->andWhere('MONTH(r.factureDatePaiement) = :month')
            ->andWhere('DAY(r.factureDatePaiement) = :day')
			->andWhere('r.contract IS NULL')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->setParameter('day', $day)
            ->orderBy('r.factureDatePaiement', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function findByYearMonthReleve($year,$month)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
        $emConfig->addCustomDatetimeFunction('DAY', 'DoctrineExtensions\Query\Mysql\Day');
        return $this->createQueryBuilder('r')
            ->where('YEAR(r.factureDatePaiement) = :year')
            ->andWhere('MONTH(r.factureDatePaiement) = :month')
			->andWhere('r.contract IS NULL')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->orderBy('r.factureDatePaiement', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function findByYearMonthDayReleve($year,$month,$day)
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        $emConfig->addCustomDatetimeFunction('MONTH', 'DoctrineExtensions\Query\Mysql\Month');
        $emConfig->addCustomDatetimeFunction('DAY', 'DoctrineExtensions\Query\Mysql\Day');
        return $this->createQueryBuilder('r')
            ->where('YEAR(r.factureDatePaiement) = :year')
            ->andWhere('MONTH(r.factureDatePaiement) = :month')
            ->andWhere('DAY(r.factureDatePaiement) = :day')
			->andWhere('r.contract IS NULL')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->setParameter('day', $day)
            ->orderBy('r.factureDatePaiement', 'DESC')
            ->getQuery()
            ->getResult();
    }


    public function findUniqueYears()
    {
        $emConfig = $this->getEntityManager()->getConfiguration();
        $emConfig->addCustomDatetimeFunction('YEAR', 'DoctrineExtensions\Query\Mysql\Year');
        return $this->createQueryBuilder('r')
            ->select('DISTINCT YEAR(r.factureDatePaiement) AS year')
            ->orderBy('year', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function add(Releve $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Releve $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return Releve[] Returns an array of Releve objects
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

//    public function findOneBySomeField($value): ?Releve
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
