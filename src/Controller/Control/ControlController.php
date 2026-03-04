<?php

namespace App\Controller\Control;

use App\Entity\Depense;
use App\Repository\ReleveRepository;
use App\Repository\PaymentTrancheRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @Route("/control")
 */
class ControlController extends AbstractController
{
    /**
     * @Route("/journal", name="journals_control")
     */
   /**
     * @Route("/journal", name="journals_control")
     */
    public function journal_control(
        Request $request, 
        PaginatorInterface $paginator, 
        ReleveRepository $releveRepository, 
        PaymentTrancheRepository $paymentTrancheRepository,
        EntityManagerInterface $em 
    ): Response {
        
        if (!$this->isGranted('ROLE_CONTROLEUR')) {
            return $this->redirectToRoute('app_login');
        }

        // --- 1. RÉCUPÉRATION DES FILTRES ---
        $selectedYear = $request->request->get('year', $request->query->get('year', date('Y')));
        $selectedMonth = $request->request->get('month', $request->query->get('month'));
        $selectedDay = $request->request->get('day', $request->query->get('day'));
        $selectJournal = $request->request->get('journal_type', $request->query->get('journal_type'));

        $years = $releveRepository->findUniqueYears();
        $months = [
            '01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril', 
            '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Août', 
            '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre'
        ];

        // --- 2. RÉCUPÉRATION DES DONNÉES BRUTES ---
        if($selectedYear && !$selectedMonth) {
            $releves = $releveRepository->findByYearReleveAudit($selectedYear);
            $paymentTranches = $paymentTrancheRepository->findByYearAudit($selectedYear);
        } elseif($selectedYear && $selectedMonth && !$selectedDay) {
            $releves = $releveRepository->findByYearMonthReleveAudit($selectedYear, $selectedMonth);
            $paymentTranches = $paymentTrancheRepository->findByYearMonthAudit($selectedYear, $selectedMonth);
        } else {
            $releves = $releveRepository->findByYearMonthDayReleveAudit($selectedYear, $selectedMonth, $selectedDay);
            $paymentTranches = $paymentTrancheRepository->findByYearMonthDayAudit($selectedYear, $selectedMonth, $selectedDay);
        }

        // --- 3. CALCUL DU TOTAL GLOBAL (AVANT PAGINATION) ---
        $combinedData = array_merge($releves, $paymentTranches);
        $totalRecettesGlobal = 0;

        foreach ($combinedData as $item) {
            if ($item instanceof \App\Entity\Releve) {
                $conso = $item->getConsomation() ?? 0;
                $pu = $item->getPu() ?? 0;
                $limite = $item->getLimite();
                $pus = $item->getPus();

                // Calcul du montant HT (Paliers)
                if ($limite !== null) {
                    if ($conso <= $limite) {
                        $numerator = $conso * $pu;
                    } else {
                        $numerator = ($limite * $pu) + (($conso - $limite) * $pus);
                    }
                } else {
                    $numerator = $conso * $pu;
                }

                // Application des taxes (5% + 1% + 2% + 2% = 10% total selon ton Twig)
                $taxe1 = ($numerator * 5) / 100;
                $taxe2 = ($numerator * 1) / 100;
                $taxe3 = ($numerator * 2) / 100;
                
                $resultat = $numerator + 1000 + $taxe1 + $taxe2 + ($taxe3 * 2);

                // Taxe supplémentaire si conso > 10
                if ($conso > 10 && $pus !== null) {
                    $taxeSpec = (($conso - 10) * $pus * 20) / 100;
                    $resultat += $taxeSpec;
                }

                // Remise client spécifique
                if ($item->getClient() && $item->getClient()->getIsSpecific()) {
                    $resultat -= 50000;
                }

                $totalRecettesGlobal += $resultat;
            } elseif ($item instanceof \App\Entity\PaymentTranche) {
                $totalRecettesGlobal += $item->getArgent() ?? 0;
            }
        }

        // --- 4. CALCUL DES DÉPENSES ---
        $repoDepense = $em->getRepository(Depense::class);
        $qbDepense = $repoDepense->createQueryBuilder('d')->select('SUM(d.montant)');

        if ($selectedDay && $selectedMonth) {
            $dateStr = "$selectedYear-$selectedMonth-$selectedDay";
            $qbDepense->where('d.createdAt LIKE :val')->setParameter('val', $dateStr . '%');
        } elseif ($selectedMonth) {
            $qbDepense->where('YEAR(d.createdAt) = :year AND MONTH(d.createdAt) = :month')
                      ->setParameter('year', $selectedYear)->setParameter('month', $selectedMonth);
        } else {
            $qbDepense->where('YEAR(d.createdAt) = :year')->setParameter('year', $selectedYear);
        }
        $totalDepensesSomme = $qbDepense->getQuery()->getSingleScalarResult() ?? 0;

        // --- 5. PAGINATION ---
        $pagination = $paginator->paginate(
            $combinedData, 
            $request->query->getInt('page', 1), 
            10 
        );

        return $this->render('control/journals_control.html.twig', [
            'releves' => $pagination,
            'totalRecettesGlobal' => $totalRecettesGlobal,
            'totalDepenses' => $totalDepensesSomme,
            'years' => $years,
            'selectedYear' => $selectedYear,
            'months' => $months,
            'selectedMonth' => $selectedMonth,
            'selectedDay' => $selectedDay,
            'selectJournal' => $selectJournal,
            'controller_name' => 'Espace Contrôleur',
        ]);
    }

   /**
     * @Route("/depenses", name="depenses_control")
     */
    public function depenses_control(
        Request $request, 
        EntityManagerInterface $em, 
        ReleveRepository $releveRepository
    ): Response {
        
        if (!$this->isGranted('ROLE_CONTROLEUR')) {
            return $this->redirectToRoute('app_login');
        }

        // 1. GESTION DE L'AJOUT
        if ($request->isMethod('POST') && $request->request->get('montant')) {
            $depense = new Depense();
            $depense->setMontant((float)$request->request->get('montant'));
            $depense->setType($request->request->get('type'));

            // RÉCUPÉRATION DE LA DATE DEPUIS LE CALENDRIER DU MODAL
            $dateSaisie = $request->request->get('date_depense');
            if ($dateSaisie) {
                // On crée l'objet DateTime à partir du format Y-m-d du calendrier
                $depense->setCreatedAt(new \DateTime($dateSaisie));
            } else {
                $depense->setCreatedAt(new \DateTime());
            }
            
            if ($this->getUser()) {
                $identifier = method_exists($this->getUser(), 'getUserIdentifier') 
                    ? $this->getUser()->getUserIdentifier() 
                    : $this->getUser()->getUsername();
                $depense->setCreatedBy($identifier);
            }

            $em->persist($depense);
            $em->flush();

            $this->addFlash('success', 'Dépense enregistrée avec succès !');
            return $this->redirectToRoute('depenses_control');
        }

        // 2. PRÉPARATION DES FILTRES
        $years = $releveRepository->findUniqueYears();
        $selectedYear = $request->request->get('year', date('Y'));
        $selectedMonth = $request->request->get('month');
        $months = [
            '01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril',
            '05'=>'Mai','06'=>'Juin','07'=>'Juillet','08'=>'Août',
            '09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'
        ];

        // 3. RÉCUPÉRATION DES DONNÉES
        $repoDepense = $em->getRepository(Depense::class);
        $qb = $repoDepense->createQueryBuilder('d');

        if ($selectedMonth) {
            $startDate = new \DateTime("$selectedYear-$selectedMonth-01 00:00:00");
            $endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);
        } else {
            $startDate = new \DateTime("$selectedYear-01-01 00:00:00");
            $endDate = new \DateTime("$selectedYear-12-31 23:59:59");
        }

        $qb->where('d.createdAt BETWEEN :start AND :end')
           ->setParameter('start', $startDate)
           ->setParameter('end', $endDate)
           ->orderBy('d.createdAt', 'DESC');

        $depenses = $qb->getQuery()->getResult();

        $totalDepenses = 0;
        foreach ($depenses as $d) { 
            $totalDepenses += $d->getMontant(); 
        }

        return $this->render('control/depenses_control.html.twig', [
            'depenses' => $depenses,
            'total' => $totalDepenses,
            'years' => $years,
            'months' => $months,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
            'controller_name' => 'Espace Contrôleur - Dépenses',
        ]);
    }

    /**
     * @Route("/depense/edit/{id}", name="depense_edit", methods={"POST"})
     */
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $depense = $em->getRepository(Depense::class)->find($id);

        if (!$depense) {
            $this->addFlash('error', 'Dépense introuvable.');
            return $this->redirectToRoute('depenses_control');
        }

        $montant = $request->request->get('montant');
        $type = $request->request->get('type');
        $dateSaisie = $request->request->get('date_depense');

        if ($montant !== null) {
            $depense->setMontant((float)$montant);
            $depense->setType($type);
            
            // MISE À JOUR DE LA DATE SI MODIFIÉE DANS LE CALENDRIER
            if ($dateSaisie) {
                $depense->setCreatedAt(new \DateTime($dateSaisie));
            }

            $em->flush();
            $this->addFlash('success', 'La dépense #' . $id . ' a été modifiée avec succès.');
        }

        return $this->redirectToRoute('depenses_control');
    }

    /**
     * @Route("/depense/delete/{id}", name="depense_delete", methods={"POST"})
     */
    public function delete(Request $request, int $id, EntityManagerInterface $em): Response
    {
        $depense = $em->getRepository(Depense::class)->find($id);

        if ($depense && $this->isCsrfTokenValid('delete'.$depense->getId(), $request->request->get('_token'))) {
            $em->remove($depense);
            $em->flush();
            $this->addFlash('success', 'Dépense supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Erreur lors de la suppression.');
        }

        return $this->redirectToRoute('depenses_control');
    }
}