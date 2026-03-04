<?php

namespace App\Controller;

use App\Entity\Contract;
use App\Entity\Releve;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\ContractRepository;
use App\Repository\FactureRepository;
use App\Repository\QuartierRepository;
use App\Repository\PaymentTrancheRepository;
use App\Repository\ReleveRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Psr\Log\LoggerInterface;
/**
 * @Route("/principale")
 */
class PrincipaleController extends AbstractController
{
    private $clientRepository;
    private $quartierRepository;
    private $entityManager;
    private $releveRepository;
    private $userRepository; 
    private $contractRepository;
private $paymentTrancheRepository;
    function __construct(
        ClientRepository $clientRepository,
        QuartierRepository $quartierRepository,
        EntityManagerInterface $entityManager,
        ReleveRepository $releveRepository,
        UserRepository $userRepository,
        ContractRepository $contractRepository,
		PaymentTrancheRepository $paymentTrancheRepository
    ) {
        $this->contractRepository=$contractRepository;
        $this->clientRepository=$clientRepository;
        $this->quartierRepository=$quartierRepository;
        $this->entityManager=$entityManager;
        $this->releveRepository=$releveRepository;
        $this->userRepository = $userRepository;
        $this->paymentTrancheRepository=$paymentTrancheRepository;
    }

    /**
     * @Route("/clients", name="principale")
     */
    public function clients(Request $request, PaginatorInterface $paginator)
    {
        if (!$this->isGranted('ROLE_PRINCIPALE')) {
            return $this->redirectToRoute('app_login');
        }
        $query = $this->clientRepository->createQueryBuilder('c');
        $searchQuery = $request->request->get('search');
        if ($searchQuery) {
            $query->andWhere('c.nom LIKE :search OR c.prenom LIKE :search OR c.code LIKE :search')
                ->setParameter('search', '%' . $searchQuery . '%');
        }

        $pagination = $paginator->paginate(
            $query, // Requête
            $request->query->getInt('page', 1), // Numéro de page
            10 // Éléments par page
        );
        return $this->render('principale/client.html.twig',[
            'client'=>$pagination,
            'search'=>$searchQuery
        ]);
    }

     /**
     * @Route("/client/{id}",name="client_principale")
     */
    public function client($id, PaginatorInterface $paginator, Request $request){
        if (!$this->isGranted('ROLE_PRINCIPALE')) {
            return $this->redirectToRoute('app_login');
        }
        $client = $this->clientRepository->find($id);
        $releveQuery = $this->releveRepository->createQueryBuilder('r')
                ->where('r.client = :client')
                ->setParameter('client', $client)
                ->orderBy('r.id', 'DESC')
                ->getQuery();

        $currentPage = $request->query->getInt('page', 1); // Récupérer le numéro de page actuel depuis la requête

        $releve = $paginator->paginate(
            $releveQuery,
            $currentPage,
            10 // Nombre d'éléments par page
        );
        return $this->render('principale/see_client.html.twig',[
            'client'=>$client,
            'releves'=>$releve
        ]);

    }

    /**
     * @Route("/annuler/payment",name="annuler_payment")
     */
    public function annuler_payment(Request $request,FactureRepository $factureRepository){
        $id = $request->request->get('id');
        $releve = $this->releveRepository->find($id);
        $facture = $factureRepository->findBy(['releve'=>$releve]);
        foreach($facture as $facture){
        $this->entityManager->remove($facture);
        }
        $this->entityManager->flush();
        $releve->setFactureDatePaiement(null);
        $releve->setPayer(null);
        $this->entityManager->persist($releve);
        $this->entityManager->flush();
        $this->addFlash('success', 'L\'annulation de paiement est ok');
        return new JsonResponse('Succès');
    }
    /**
     * @Route("/payment/tranche",name="payment_tranche")
     */
    public function payment_tranche(Request $request)
    {
        $id = $request->request->get('id');
        $releve = $request->request->get('releves');
        if($releve != null )
        {
            $totalMontantFactures = 0;
            foreach($releve as $releves){
                $rel = $this->releveRepository->find($releves);
                $limite = $rel->getLimite();
                $consomation = $rel->getConsomation();
                $pu = $rel->getPu();
                $pus = $rel->getPus();
                if ($limite != null) {
                    if ($consomation > $limite) {
                        $consomation1 = $limite;
                        $consomation2= $consomation - $limite;
                        $valeur1 = $consomation1 * $pu;
                        $valeur2 = $consomation2 * $pus;
                        $totale = $valeur1 + $valeur2;
                        $totaleHT = $totale + 1000;
                        $surtaxe = ($totale * 5) / 100 ;
                        $taxeCommunale = ($totale * 1) / 100 ;
                        $audit = ($totale * 2) / 100 ;
                        $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                        if( $consomation > 10 and $pus != null){
                            $constva = ($consomation - 10) * $pus;
                            $tva = ($constva * 20) / 100;
                            $totaleFinale = $totaleHT + $totalTaxe + $tva;
                        }else{
                            $tva = 0;
                            $totaleFinale = $totaleHT + $totalTaxe;
        
                        }
                    }else{
                        $consomation1 = $consomation;
                        $consomation2 = 0;
                        $totale = $consomation1 * $pu;
                        $totaleHT = $totale + 1000;
                        $surtaxe = ($totale * 5) / 100 ;
                        $taxeCommunale = ($totale * 1) / 100 ;
                        $audit = ($totale * 2) / 100 ;
                        $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                        if( $consomation > 10 and $pus != null){
                            $constva = ($consomation - 10) * $pus;
                            $tva = ($constva * 20) / 100;
                            $totaleFinale = $totaleHT + $totalTaxe + $tva;
                        }else{
                            $tva = 0;
                            $totaleFinale = $totaleHT + $totalTaxe;
                        }
                    }
                }else{
                    $consomation1 = $consomation;
                    $consomation2 = 0;
                    $totale = $consomation1 * $pu;
                    $totaleHT = $totale + 1000;
                    $surtaxe = ($totale * 5) / 100 ;
                    $taxeCommunale = ($totale * 1) / 100 ;
                    $audit = ($totale * 2) / 100 ;
                    $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                    if( $consomation > 10 and $pus != null){
                        $constva = ($consomation - 10) * $pus;
                        $tva = ($constva * 20) / 100;
                        $totaleFinale = $totaleHT + $totalTaxe + $tva;
                    }else{
                        $tva = 0;
                        $totaleFinale = $totaleHT + $totalTaxe;
        
                    }
                   
        
                }
                $totalMontantFactures += $totaleFinale;
            }
            $client = $this->releveRepository->find($releve[0]);
            $clients = $client->getClient();
            $contract = new Contract();
            $contract->setTotalAMount($totalMontantFactures);
            $contract->setClient($clients);
            $this->entityManager->persist($contract);
            $this->entityManager->flush();
            foreach($releve as $releves){
                $rel = $this->releveRepository->find($releves);
                $rel->setContract($contract);
                $this->entityManager->persist($rel);
            }
            $this->entityManager->flush();

        }
        
        if ($id != null) {
            $releve = $this->releveRepository->find($id);
            $limite = $releve->getLimite();
            $consomation = $releve->getConsomation();
            $pu = $releve->getPu();
            $pus = $releve->getPus();
            if ($limite != null) {
                if ($consomation > $limite) {
                    $consomation1 = $limite;
                    $consomation2= $consomation - $limite;
                    $valeur1 = $consomation1 * $pu;
                    $valeur2 = $consomation2 * $pus;
                    $totale = $valeur1 + $valeur2;
                    $totaleHT = $totale + 1000;
                    $surtaxe = ($totale * 5) / 100 ;
                    $taxeCommunale = ($totale * 1) / 100 ;
                    $audit = ($totale * 2) / 100 ;
                    $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                    if( $consomation > 10 and $pus != null){
                        $constva = ($consomation - 10) * $pus;
                        $tva = ($constva * 20) / 100;
                        $totaleFinale = $totaleHT + $totalTaxe + $tva;
                    }else{
                        $tva = 0;
                        $totaleFinale = $totaleHT + $totalTaxe;
    
                    }
                }else{
                    $consomation1 = $consomation;
                    $consomation2 = 0;
                    $totale = $consomation1 * $pu;
                    $totaleHT = $totale + 1000;
                    $surtaxe = ($totale * 5) / 100 ;
                    $taxeCommunale = ($totale * 1) / 100 ;
                    $audit = ($totale * 2) / 100 ;
                    $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                    if( $consomation > 10 and $pus != null){
                        $constva = ($consomation - 10) * $pus;
                        $tva = ($constva * 20) / 100;
                        $totaleFinale = $totaleHT + $totalTaxe + $tva;
                    }else{
                        $tva = 0;
                        $totaleFinale = $totaleHT + $totalTaxe;
                    }
                }
            }else{
                $consomation1 = $consomation;
                $consomation2 = 0;
                $totale = $consomation1 * $pu;
                $totaleHT = $totale + 1000;
                $surtaxe = ($totale * 5) / 100 ;
                $taxeCommunale = ($totale * 1) / 100 ;
                $audit = ($totale * 2) / 100 ;
                $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                if( $consomation > 10 and $pus != null){
                    $constva = ($consomation - 10) * $pus;
                    $tva = ($constva * 20) / 100;
                    $totaleFinale = $totaleHT + $totalTaxe + $tva;
                }else{
                    $tva = 0;
                    $totaleFinale = $totaleHT + $totalTaxe;
    
                }
               
    
            }
            $client = $releve->getClient();
            $contract = new Contract();
            $contract->setTotalAMount($totaleFinale);
            $contract->setClient($client);
            $this->entityManager->persist($contract);
            $this->entityManager->flush();
            $releve->setContract($contract);
            $this->entityManager->persist($releve);
            $this->entityManager->flush();
          
        }
        return $this->redirectToRoute('tranche');
    }

    /**
     * @Route("/tranche",name="tranche")
     */
    public function tranche(){

        $contract = $this->contractRepository->findAll();
        return $this->render('principale/contract.html.twig',[
            'contrat'=>$contract
        ]);
    }
    

    /**
     * @Route("/detail/tranche/contrat/{id}",name="detail_tranche")
     */
    public function detail_tranche($id)
    {
        $contrat = $this->contractRepository->find($id);
        $releve = $this->releveRepository->findBy(['contract'=>$contrat]);
        return $this->render('principale/see_contract.html.twig',[
            'contrat'=>$contrat,
            'releve'=>$releve
        ]);
    }

     /**
     * @Route("/journals",name="journal_principale")
     */
    public function journal_principale(Request $request,PaginatorInterface $paginator,PaymentTrancheRepository $paymentTrancheRepository)
    {
        if (!$this->isGranted('ROLE_PRINCIPALE')) {
            return $this->redirectToRoute('app_login');
        }
        $years = $this->releveRepository->findUniqueYears();
        $currentYear = date('Y');
        $selectedYear = $request->request->get('year', $currentYear);
        $months = [
          'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
        ];
        $selectedMonth = $request->request->get('month');
        $selectedDay = $request->request->get('day');
        $selectJournal = $request->request->get('journal_type');
        if ($request->query->has('selectedYear')) {
          // Si les paramètres sont dans l'URL, récupérer les valeurs via query
          $selectedYear = $request->query->get('selectedYear');
          $selectedMonth = $request->query->get('selectedMonth');
          $selectedDay = $request->query->get('selectedDay');
          $selectJournal = $request->query->get('selectJournal');
      }
      if($selectedYear != null and $selectedMonth == null and $selectedDay == null)
      {
          $releves = $this->releveRepository->findByYearReleve($selectedYear);
		  $paymentTranches = $this->paymentTrancheRepository->findByYear($selectedYear);
		  
              
      }elseif($selectedYear != null and $selectedMonth != null and $selectedDay == null and $selectJournal="mensuel"){   
          $releves = $this->releveRepository->findByYearMonthReleve($selectedYear,$selectedMonth);
		  $paymentTranches = $this->paymentTrancheRepository->findByYearMonth($selectedYear, $selectedMonth);
      }else{
          $releves = $this->releveRepository->findByYearMonthDayReleve($selectedYear, $selectedMonth, $selectedDay);
		  $paymentTranches = $this->paymentTrancheRepository->findByYearMonthDay($selectedYear, $selectedMonth, $selectedDay);
      }
	  $combinedData = array_merge($releves, $paymentTranches);
      $pagination = $paginator->paginate(
        $combinedData, 
        $request->query->getInt('page', 1), 
        10 
     );
      return $this->render('principale/journal.html.twig', [
          'releves' => $pagination,
          'years' => $years,
          'selectedYear' => $selectedYear,   
          'months'=>$months,
          'selectedMonth'=>$selectedMonth,
          'selectedDay'=>$selectedDay,
          'selectJournal'=>$selectJournal

      ]);
    }

    private function moisEnLettres($moisEnChiffre) {
        $mois = [
            '1' => 'janvier',
            '2' => 'février',
            '3' => 'mars',
            '4' => 'avril',
            '5' => 'mai',
            '6' => 'juin',
            '7' => 'juillet',
            '8' => 'août',
            '9' => 'septembre',
            '10' => 'octobre',
            '11' => 'novembre',
            '12' => 'décembre',
        ];

        return $mois[$moisEnChiffre];
    }

/**
 * @Route("/generer/principale/excel/{year}/mois/{month}", name="generer_principale_excel_mois")
 */
public function generer_principale_excel_mois($year,$month)
{
    
    $selectedYear = (int)$year;
    $releves = $this->releveRepository->findByYearMonthReleve($selectedYear,$month);
	$paymentTranches = $this->paymentTrancheRepository->findByYearMonth($selectedYear, $month);
    $spreadsheet = new Spreadsheet();
    
   
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
        'Date',
        'MOIS',
        'Numero',
        'Nom Prenoms',
        'Quartier',
        'Conso Totale',
        '1e Tranche',
        'PU 1e Tranche',
        '2e Tranche',
        'PU 2e Tranche',
        'Prime Fixe',
        'Surtaxe',
        'Taxe Communale',
        'Redevence Audit',
        'Redevance Assainissement',
        'TOTAL taxe et redev.',
        'TVA',
        'Montant HT',
        'Montant Total',
    ];

    // Ajoutez les en-têtes à la feuille Excel
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    $row = 2; // Commencez à partir de la ligne 2 car la première ligne contient les en-têtes

    foreach ($releves as $releve) {
        $mois = $releve->getMois();
        $anne = $releve->getAnnee();
        $moisv = $this->moisEnLettres($mois).'-'.$anne;
        $nom = $releve->getClient()->getNom().' '.$releve->getClient()->getPrenom();
        $limite = $releve->getLimite();
        $consomation = $releve->getConsomation();
        $pu = $releve->getPu();
        $pus = $releve->getPus();
        if ($limite != null) {
            if ($consomation > $limite) {
                $consomation1 = $limite;
                $consomation2= $consomation - $limite;
                $valeur1 = $consomation1 * $pu;
                $valeur2 = $consomation2 * $pus;
                $totale = $valeur1 + $valeur2;
                $totaleHT = $totale + 1000;
                $surtaxe = ($totale * 5) / 100 ;
                $taxeCommunale = ($totale * 1) / 100 ;
                $audit = ($totale * 2) / 100 ;
                $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                if( $consomation > 10 and $pus != null){
                    $constva = ($consomation - 10) * $pus;
                    $tva = ($constva * 20) / 100;
                    $totaleFinale = $totaleHT + $totalTaxe + $tva;
                }else{
                    $tva = 0;
                    $totaleFinale = $totaleHT + $totalTaxe;

                }
            }else{
                $consomation1 = $consomation;
                $consomation2 = 0;
                $totale = $consomation1 * $pu;
                $totaleHT = $totale + 1000;
                $surtaxe = ($totale * 5) / 100 ;
                $taxeCommunale = ($totale * 1) / 100 ;
                $audit = ($totale * 2) / 100 ;
                $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                if( $consomation > 10 and $pus != null){
                    $constva = ($consomation - 10) * $pus;
                    $tva = ($constva * 20) / 100;
                    $totaleFinale = $totaleHT + $totalTaxe + $tva;
                }else{
                    $tva = 0;
                    $totaleFinale = $totaleHT + $totalTaxe;
                }
            }
        }else{
            $consomation1 = $consomation;
            $consomation2 = 0;
            $totale = $consomation1 * $pu;
            $totaleHT = $totale + 1000;
            $surtaxe = ($totale * 5) / 100 ;
            $taxeCommunale = ($totale * 1) / 100 ;
            $audit = ($totale * 2) / 100 ;
            $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
            if( $consomation > 10 and $pus != null){
                $constva = ($consomation - 10) * $pus;
                $tva = ($constva * 20) / 100;
                $totaleFinale = $totaleHT + $totalTaxe + $tva;
            }else{
                $tva = 0;
                $totaleFinale = $totaleHT + $totalTaxe;

            }

        }
		 if ($releve->getClient()->getIsSpecific()) {
				$totaleFinale -= 50000;
			}
        $sheet->setCellValue('A' . $row, $releve->getFactureDatePaiement()->format('d/m/Y'));
        $sheet->setCellValue('B' . $row, $moisv); 
        $sheet->setCellValue('C' . $row, $releve->getClient()->getCode()); 
        $sheet->setCellValue('D' . $row, $nom); 
        $sheet->setCellValue('E' . $row, $releve->getClient()->getQuartier()->getNom());
        $sheet->setCellValue('F' . $row, $consomation); 
        $sheet->setCellValue('G' . $row, $consomation1); 
        $sheet->setCellValue('H' . $row, $pu); 
        $sheet->setCellValue('I' . $row, $consomation2); 
        $sheet->setCellValue('J' . $row, $pus); 
        $sheet->setCellValue('K' . $row, '1000'); 
        $sheet->setCellValue('L' . $row, $surtaxe); 
        $sheet->setCellValue('M' . $row, $taxeCommunale); 
        $sheet->setCellValue('N' . $row, $audit);
        $sheet->setCellValue('O' . $row, $audit); 
        $sheet->setCellValue('P' . $row, $totalTaxe); 
        $sheet->setCellValue('Q' . $row, $tva); 
        $sheet->setCellValue('R' . $row, $totaleHT);
        $sheet->setCellValue('S' . $row, $totaleFinale);

        $row++;
    }

      foreach ($paymentTranches as $paymentTranches) {
        $noms = $paymentTranches->getContrat()->getClient()->getNom().' '.$paymentTranches->getContrat()->getClient()->getPrenom();
        $sheet->setCellValue('A' . $row, $paymentTranches->getDate()->format('d/m/Y'));
        $sheet->setCellValue('B' . $row, ''); 
        $sheet->setCellValue('C' . $row, $paymentTranches->getContrat()->getClient()->getCode()); 
        $sheet->setCellValue('D' . $row, $noms); 
        $sheet->setCellValue('E' . $row, $paymentTranches->getContrat()->getClient()->getQuartier()->getNom());
        $sheet->setCellValue('F' . $row, ''); 
        $sheet->setCellValue('G' . $row, ''); 
        $sheet->setCellValue('H' . $row, ''); 
        $sheet->setCellValue('I' . $row, ''); 
        $sheet->setCellValue('J' . $row, ''); 
        $sheet->setCellValue('K' . $row, ''); 
        $sheet->setCellValue('L' . $row, ''); 
        $sheet->setCellValue('M' . $row, ''); 
        $sheet->setCellValue('N' . $row, '');
        $sheet->setCellValue('O' . $row, ''); 
        $sheet->setCellValue('P' . $row, ''); 
        $sheet->setCellValue('Q' . $row, ''); 
        $sheet->setCellValue('R' . $row, '');
        $sheet->setCellValue('S' . $row, $paymentTranches->getArgent());

        $row++;
    }


    // Créez un objet Writer (classe PhpSpreadsheet\Writer\Xlsx)
    $writer = new Xlsx($spreadsheet);

    // Configurez le chemin du fichier temporaire où le fichier Excel sera sauvegardé
    $tempFilePath = tempnam(sys_get_temp_dir(), 'journal_');

    // Sauvegardez le fichier Excel dans le chemin temporaire
    $writer->save($tempFilePath);

    // Créez une réponse HTTP pour le téléchargement du fichier Excel
    $response = new Response(file_get_contents($tempFilePath));
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $date = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
    $timestamp = $date->format('Y-m-d_H-i-s');
    
    $filename = 'journal_de_caisse_' . $timestamp . '.xlsx';
    
    $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
   
    $response->headers->set('Cache-Control', 'max-age=0');

    // Supprimez le fichier temporaire après l'avoir téléchargé
    unlink($tempFilePath);

    return $response;
}
	
/**
 * @Route ("/deletes/contrat", name="delete_contrat")
 */
public function deleteContrat(Request $request,PaymentTrancheRepository $paymentTrancheRepository)
{
    $id = $request->request->get('id');
    $delete = false;

    if ($id != null) {
        $contract = $this->contractRepository->find($id);

        if ($contract) {
            // Handle releve entities
            $releve = $this->releveRepository->findBy(['contract' => $contract]);
            foreach ($releve as $releves) {
                $releves->setContract(NULL);
                $this->entityManager->persist($releves);
            }
            $paymentTranches = $paymentTrancheRepository->findBy(['contrat' => $contract]);
            foreach ($paymentTranches as $tranche) {
               $this->entityManager->remove($tranche);
            }

            // Flush changes to releve and payment tranche entities
            $this->entityManager->flush();

            // Now remove the contract
            $this->entityManager->remove($contract);
            $this->entityManager->flush();

            $delete = true;
            $this->addFlash('success', "Success: Contract deleted.");
        } else {
            $this->addFlash('error', "Error: Contract not found.");
        }
    } else {
        $this->addFlash('error', "Error: Invalid contract ID.");
    }

    return new JsonResponse(['form_delete' => $delete]);
}

/**
 * @Route("/generates/principale/excel/{year}", name="generates_excel_principale")
 */
public function generatesPrincipaleExcel($year)
{
    
    $selectedYear = (int)$year;
    $releves = $this->releveRepository->findByYearReleve($selectedYear);
	$paymentTranches = $this->paymentTrancheRepository->findByYear($selectedYear);
    $spreadsheet = new Spreadsheet();
    
   
    $sheet = $spreadsheet->getActiveSheet();

    
    $headers = [
        'Date',
        'MOIS',
        'Numero',
        'Nom Prenoms',
        'Quartier',
        'Conso Totale',
        '1e Tranche',
        'PU 1e Tranche',
        '2e Tranche',
        'PU 2e Tranche',
        'Prime Fixe',
        'Surtaxe',
        'Taxe Communale',
        'Redevence Audit',
        'Redevance Assainissement',
        'TOTAL taxe et redev.',
        'TVA',
        'Montant HT',
        'Montant Total',
    ];

    // Ajoutez les en-têtes à la feuille Excel
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    $row = 2; // Commencez à partir de la ligne 2 car la première ligne contient les en-têtes

    foreach ($releves as $releve) {
        $mois = $releve->getMois();
        $anne = $releve->getAnnee();
      
        $moisv = $this->moisEnLettres($mois).'-'.$anne;
        $nom = $releve->getClient()->getNom().' '.$releve->getClient()->getPrenom();
        $limite = $releve->getLimite();
        $consomation = $releve->getConsomation();
        $pu = $releve->getPu();
        $pus = $releve->getPus();
        if ($limite != null) {
            if ($consomation > $limite) {
                $consomation1 = $limite;
                $consomation2= $consomation - $limite;
                $valeur1 = $consomation1 * $pu;
                $valeur2 = $consomation2 * $pus;
                $totale = $valeur1 + $valeur2;
                $totaleHT = $totale + 1000;
                $surtaxe = ($totale * 5) / 100 ;
                $taxeCommunale = ($totale * 1) / 100 ;
                $audit = ($totale * 2) / 100 ;
                $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                if( $consomation > 10 and $pus != null){
                    $constva = ($consomation - 10) * $pus;
                    $tva = ($constva * 20) / 100;
                    $totaleFinale = $totaleHT + $totalTaxe + $tva;
                }else{
                    $tva = 0;
                    $totaleFinale = $totaleHT + $totalTaxe;

                }
            }else{
                $consomation1 = $consomation;
                $consomation2 = 0;
                $totale = $consomation1 * $pu;
                $totaleHT = $totale + 1000;
                $surtaxe = ($totale * 5) / 100 ;
                $taxeCommunale = ($totale * 1) / 100 ;
                $audit = ($totale * 2) / 100 ;
                $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                if( $consomation > 10 and $pus != null){
                    $constva = ($consomation - 10) * $pus;
                    $tva = ($constva * 20) / 100;
                    $totaleFinale = $totaleHT + $totalTaxe + $tva;
                }else{
                    $tva = 0;
                    $totaleFinale = $totaleHT + $totalTaxe;
                }
            }
        }else{
            $consomation1 = $consomation;
            $consomation2 = 0;
            $totale = $consomation1 * $pu;
            $totaleHT = $totale + 1000;
            $surtaxe = ($totale * 5) / 100 ;
            $taxeCommunale = ($totale * 1) / 100 ;
            $audit = ($totale * 2) / 100 ;
            $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
            if( $consomation > 10 and $pus != null){
                $constva = ($consomation - 10) * $pus;
                $tva = ($constva * 20) / 100;
                $totaleFinale = $totaleHT + $totalTaxe + $tva;
            }else{
                $tva = 0;
                $totaleFinale = $totaleHT + $totalTaxe;

            }

        }
		 if ($releve->getClient()->getIsSpecific()) {
				$totaleFinale -= 50000;
			}
        $sheet->setCellValue('A' . $row, $releve->getFactureDatePaiement()->format('d/m/Y'));
        $sheet->setCellValue('B' . $row, $moisv); 
        $sheet->setCellValue('C' . $row, $releve->getClient()->getCode()); 
        $sheet->setCellValue('D' . $row, $nom); 
        $sheet->setCellValue('E' . $row, $releve->getClient()->getQuartier()->getNom());
        $sheet->setCellValue('F' . $row, $consomation); 
        $sheet->setCellValue('G' . $row, $consomation1); 
        $sheet->setCellValue('H' . $row, $pu); 
        $sheet->setCellValue('I' . $row, $consomation2); 
        $sheet->setCellValue('J' . $row, $pus); 
        $sheet->setCellValue('K' . $row, '1000'); 
        $sheet->setCellValue('L' . $row, $surtaxe); 
        $sheet->setCellValue('M' . $row, $taxeCommunale); 
        $sheet->setCellValue('N' . $row, $audit);
        $sheet->setCellValue('O' . $row, $audit); 
        $sheet->setCellValue('P' . $row, $totalTaxe); 
        $sheet->setCellValue('Q' . $row, $tva); 
        $sheet->setCellValue('R' . $row, $totaleHT);
        $sheet->setCellValue('S' . $row, $totaleFinale);

        $row++;
    }
	
	  foreach ($paymentTranches as $paymentTranches) {
        $noms = $paymentTranches->getContrat()->getClient()->getNom().' '.$paymentTranches->getContrat()->getClient()->getPrenom();
        $sheet->setCellValue('A' . $row, $paymentTranches->getDate()->format('d/m/Y'));
        $sheet->setCellValue('B' . $row, ''); 
        $sheet->setCellValue('C' . $row, $paymentTranches->getContrat()->getClient()->getCode()); 
        $sheet->setCellValue('D' . $row, $noms); 
        $sheet->setCellValue('E' . $row, $paymentTranches->getContrat()->getClient()->getQuartier()->getNom());
        $sheet->setCellValue('F' . $row, ''); 
        $sheet->setCellValue('G' . $row, ''); 
        $sheet->setCellValue('H' . $row, ''); 
        $sheet->setCellValue('I' . $row, ''); 
        $sheet->setCellValue('J' . $row, ''); 
        $sheet->setCellValue('K' . $row, ''); 
        $sheet->setCellValue('L' . $row, ''); 
        $sheet->setCellValue('M' . $row, ''); 
        $sheet->setCellValue('N' . $row, '');
        $sheet->setCellValue('O' . $row, ''); 
        $sheet->setCellValue('P' . $row, ''); 
        $sheet->setCellValue('Q' . $row, ''); 
        $sheet->setCellValue('R' . $row, '');
        $sheet->setCellValue('S' . $row, $paymentTranches->getArgent());

        $row++;
    }

    

    // Créez un objet Writer (classe PhpSpreadsheet\Writer\Xlsx)
    $writer = new Xlsx($spreadsheet);

    // Configurez le chemin du fichier temporaire où le fichier Excel sera sauvegardé
    $tempFilePath = tempnam(sys_get_temp_dir(), 'journal_');


    $writer->save($tempFilePath);

    $response = new Response(file_get_contents($tempFilePath));
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $date = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
    $timestamp = $date->format('Y-m-d_H-i-s');
    
    $filename = 'journal_de_caisse_' . $timestamp . '.xlsx';
    
    $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
    $response->headers->set('Cache-Control', 'max-age=0');

    // Supprimez le fichier temporaire après l'avoir téléchargé
    unlink($tempFilePath);

    return $response;
}
/**
 * @Route("/generates/principale/excel/year/{year}/month/{month}/day/{day}", name="generates_principale_excel_day")
 */
public function generates_principale_excel_day($year,$month,$day)
{
    $releves = $this->releveRepository->findByYearMonthDayReleve($year,$month,$day);
	$paymentTranches = $this->paymentTrancheRepository->findByYearMonthDay($year, $month, $day);
    $spreadsheet = new Spreadsheet();
    
   
    $sheet = $spreadsheet->getActiveSheet();

    
    $headers = [
        'Date',
        'MOIS',
        'Numero',
        'Nom Prenoms',
        'Quartier',
        'Conso Totale',
        '1e Tranche',
        'PU 1e Tranche',
        '2e Tranche',
        'PU 2e Tranche',
        'Prime Fixe',
        'Surtaxe',
        'Taxe Communale',
        'Redevence Audit',
        'Redevance Assainissement',
        'TOTAL taxe et redev.',
        'TVA',
        'Montant HT',
        'Montant Total',
    ];


    // Ajoutez les en-têtes à la feuille Excel
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    $row = 2; // Commencez à partir de la ligne 2 car la première ligne contient les en-têtes

    foreach ($releves as $releve) {
        $mois = $releve->getMois();
        $anne = $releve->getAnnee();
        $moisv = $this->moisEnLettres($mois).'-'.$anne;
        $nom = $releve->getClient()->getNom().' '.$releve->getClient()->getPrenom();
        $limite = $releve->getLimite();
        $consomation = $releve->getConsomation();
        $pu = $releve->getPu();
        $pus = $releve->getPus();
        if ($limite != null) {
            if ($consomation > $limite) {
                $consomation1 = $limite;
                $consomation2= $consomation - $limite;
                $valeur1 = $consomation1 * $pu;
                $valeur2 = $consomation2 * $pus;
                $totale = $valeur1 + $valeur2;
                $totaleHT = $totale + 1000;
                $surtaxe = ($totale * 5) / 100 ;
                $taxeCommunale = ($totale * 1) / 100 ;
                $audit = ($totale * 2) / 100 ;
                $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                if( $consomation > 10 and $pus != null){
                    $constva = ($consomation - 10) * $pus;
                    $tva = ($constva * 20) / 100;
                    $totaleFinale = $totaleHT + $totalTaxe + $tva;
                }else{
                    $tva=0;
                    $totaleFinale = $totaleHT + $totalTaxe;

                }
            }else{
                $consomation1 = $consomation;
                $consomation2 = 0;
                $totale = $consomation1 * $pu;
                $totaleHT = $totale + 1000;
                $surtaxe = ($totale * 5) / 100 ;
                $taxeCommunale = ($totale * 1) / 100 ;
                $audit = ($totale * 2) / 100 ;
                $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                if( $consomation > 10 and $pus != null){
                    $constva = ($consomation - 10) * $pus;
                    $tva = ($constva * 20) / 100;
                    $totaleFinale = $totaleHT + $totalTaxe + $tva;
                }else{
                    $tva = 0;
                    $totaleFinale = $totaleHT + $totalTaxe;
                }
            }
        }else{
            $consomation1 = $consomation;
            $consomation2 = 0;
            $totale = $consomation1 * $pu;
            $totaleHT = $totale + 1000;
            $surtaxe = ($totale * 5) / 100 ;
            $taxeCommunale = ($totale * 1) / 100 ;
            $audit = ($totale * 2) / 100 ;
            $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
            if( $consomation > 10 and $pus != null){
                $constva = ($consomation - 10) * $pus;
                $tva = ($constva * 20) / 100;
                $totaleFinale = $totaleHT + $totalTaxe + $tva;
            }else{
                $tva = 0;
                $totaleFinale = $totaleHT + $totalTaxe;

            }

        }
		 if ($releve->getClient()->getIsSpecific()) {
				$totaleFinale -= 50000;
			}
        $sheet->setCellValue('A' . $row, $releve->getFactureDatePaiement()->format('d/m/Y'));
        $sheet->setCellValue('B' . $row, $moisv); 
        $sheet->setCellValue('C' . $row, $releve->getClient()->getCode()); 
        $sheet->setCellValue('D' . $row, $nom); 
        $sheet->setCellValue('E' . $row, $releve->getClient()->getQuartier()->getNom());
        $sheet->setCellValue('F' . $row, $consomation); 
        $sheet->setCellValue('G' . $row, $consomation1); 
        $sheet->setCellValue('H' . $row, $pu); 
        $sheet->setCellValue('I' . $row, $consomation2); 
        $sheet->setCellValue('J' . $row, $pus); 
        $sheet->setCellValue('K' . $row, '1000'); 
        $sheet->setCellValue('L' . $row, $surtaxe); 
        $sheet->setCellValue('M' . $row, $taxeCommunale); 
        $sheet->setCellValue('N' . $row, $audit);
        $sheet->setCellValue('O' . $row, $audit); 
        $sheet->setCellValue('P' . $row, $totalTaxe); 
        $sheet->setCellValue('Q' . $row, $tva); 
        $sheet->setCellValue('R' . $row, $totaleHT);
        $sheet->setCellValue('S' . $row, $totaleFinale);

        $row++;
    }
	
	foreach ($paymentTranches as $paymentTranches) {
        $noms = $paymentTranches->getContrat()->getClient()->getNom().' '.$paymentTranches->getContrat()->getClient()->getPrenom();
        $sheet->setCellValue('A' . $row, $paymentTranches->getDate()->format('d/m/Y'));
        $sheet->setCellValue('B' . $row, ''); 
        $sheet->setCellValue('C' . $row, $paymentTranches->getContrat()->getClient()->getCode()); 
        $sheet->setCellValue('D' . $row, $noms); 
        $sheet->setCellValue('E' . $row, $paymentTranches->getContrat()->getClient()->getQuartier()->getNom());
        $sheet->setCellValue('F' . $row, ''); 
        $sheet->setCellValue('G' . $row, ''); 
        $sheet->setCellValue('H' . $row, ''); 
        $sheet->setCellValue('I' . $row, ''); 
        $sheet->setCellValue('J' . $row, ''); 
        $sheet->setCellValue('K' . $row, ''); 
        $sheet->setCellValue('L' . $row, ''); 
        $sheet->setCellValue('M' . $row, ''); 
        $sheet->setCellValue('N' . $row, '');
        $sheet->setCellValue('O' . $row, ''); 
        $sheet->setCellValue('P' . $row, ''); 
        $sheet->setCellValue('Q' . $row, ''); 
        $sheet->setCellValue('R' . $row, '');
        $sheet->setCellValue('S' . $row, $paymentTranches->getArgent());

        $row++;
    }

    

    // Créez un objet Writer (classe PhpSpreadsheet\Writer\Xlsx)
    $writer = new Xlsx($spreadsheet);

    // Configurez le chemin du fichier temporaire où le fichier Excel sera sauvegardé
    $tempFilePath = tempnam(sys_get_temp_dir(), 'journal_');

    // Sauvegardez le fichier Excel dans le chemin temporaire
    $writer->save($tempFilePath);

    // Créez une réponse HTTP pour le téléchargement du fichier Excel
    $response = new Response(file_get_contents($tempFilePath));
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $date = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
    $timestamp = $date->format('Y-m-d_H-i-s');
    
    $filename = 'journal_de_caisse_' . $timestamp . '.xlsx';
    
    $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
   
    $response->headers->set('Cache-Control', 'max-age=0');

    // Supprimez le fichier temporaire après l'avoir téléchargé
    unlink($tempFilePath);

    return $response;
}
    /**
    * @Route("/nombre/facture",name="nombre_facture")
    */
    public function getNombreFacture(FactureRepository $factureRepository)
    {
        $factures = $factureRepository->findAll(); // Récupérer toutes les factures, vous pouvez ajuster la requête selon vos besoins
    
        $resultats = [];
    
        foreach ($factures as $facture) {
            $mois = $facture->getDate()->format('Y-m');
            $caisse = $facture->getCaisse();
    
            // Vérifier si la caisse existe avant d'appeler getFullName
            if ($caisse !== null) {
                $comptable = $caisse->getFullName();
            } else {
                $comptable = 'Sans Caisse'; // Valeur par défaut si pas de caisse associée
            }
    
            if (!isset($resultats[$mois][$comptable])) {
                $resultats[$mois][$comptable]['nombre_factures'] = 0;
                $resultats[$mois][$comptable]['montant_total'] = 0;
            }
    
            $resultats[$mois][$comptable]['nombre_factures']++;
            $resultats[$mois][$comptable]['montant_total'] += $facture->getMontant();
        }
    
        return $this->render('principale/factures_par_mois.html.twig', [
            'resultats' => $resultats,
        ]);
    }
    

     /**
     * @Route("/utilisateur",name="user_add")
     */
    public function user_add(){
        $utilisateur = $this->userRepository->findAll();
        return $this->render('principale/utilisateur.html.twig',[
         'utilisateur'=>$utilisateur
     ]);
     }

     /**
      * @Route("/register/edit_user",name="register_edit_user")
      */
      public function register_edit_user(Request $request, UserPasswordEncoderInterface $passwordEncoder){
        $nom = $request->request->get('nom');
        $password = $request->request->get('password'); 
        $id = $request->request->get('id');
        $user = $this->userRepository->find($id);
        $user->setFullName($nom);
        $encodedPassword = $passwordEncoder->encodePassword($user, $password);
        $user->setPassword($encodedPassword);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
          return $this->redirectToRoute('user_add'); 

      }
 /**
  * @Route("/register/user",name="register_user")
  */
     public function registerUser(Request $request, UserPasswordEncoderInterface $passwordEncoder)
     {
    
     $user = new User();
 
     // Récupérez les données du formulaire
     $username = $request->request->get('username');
     $nom = $request->request->get('nom');
     $password = $request->request->get('password'); 
     $role = $request->request->get('role');
 
     // Définissez les propriétés de l'utilisateur
     $user->setUsername($username);
     $user->setFullName($nom);
     if($role =="admin"){
         $user->setRoles(['ROLE_ADMIN']);
     }elseif($role =="caisse"){
         $user->setRoles(['ROLE_CAISSE']);
         }elseif($role =="controleur"){
         $user->setRoles(['ROLE_CONTROLEUR']);
     }else{
		 $user->setRoles(['ROLE_AUDIT']);
	 }
     
     // Encodez et définissez le mot de passe
     $encodedPassword = $passwordEncoder->encodePassword($user, $password);
     $user->setPassword($encodedPassword);
 
     // Enregistrez l'utilisateur en base de données
     $this->entityManager->persist($user);
     $this->entityManager->flush();
 
     return $this->redirectToRoute('user_add'); 
 }
 /**
  * @Route("/delete_client",name="delete_client")
  */
  public function delete_client(Request $request){
     $id = $request->request->get('id');
     $user = $this->userRepository->find($id);
     $this->entityManager->remove($user);
     $this->entityManager->flush();
     $this->addFlash('success', 'Succès de la suppression de l\'utilisateur');
    return new JsonResponse('Succès');
  }
  
    /**
     * @Route("/add/form/edit/user",name="add_form_edit_user")
     */
    public function EditUser(Request $request)
    {
       
        $id = $request->request->get('id');
        $user = $this->userRepository->find($id);
        $response = $this->renderView('principale/_form_edit_utilisateur.html.twig', [
          'user'=>$user 
        ]);

        return new JsonResponse(['form_html' => $response]);

    }
    /**
 * @Route("/dashboard",name="dashboard")
 */
public function dashboard(Request $request, PaginatorInterface $paginator){
    $years = $this->releveRepository->findUniqueYears();
    $currentYear = date('Y');
    $annee = $request->request->get('year', $currentYear);
    $months = [
      'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
    ];
    $mois = $request->request->get('month');
    if ($request->query->has('selectedYear') and $mois == null) {
        // Si les paramètres sont dans l'URL, récupérer les valeurs via query
        $annee = $request->query->get('selectedYear');
        $mois = $request->query->get('selectedMonth');
       
    }
      // Récupérer le nombre total de relevés pour une année et un mois spécifiques
      $nombreTotalReleves = $this->releveRepository
      ->count(['annee' => $annee, 'mois' => $mois]);
	
	$relevesTotale= $this->releveRepository
      ->createQueryBuilder('r')
      ->where('r.annee = :annee')
      ->andWhere('r.mois = :mois')
      ->setParameter('annee', $annee)
      ->setParameter('mois', $mois)
      ->getQuery()
      ->getResult();

	$sommeTotaleFinale = 0;

	foreach ($relevesTotale as $releve) {
		$limite = $releve->getLimite();
		$consomation = $releve->getConsomation();
		$pu = $releve->getPu();
		$pus = $releve->getPus();

		if ($limite != null) {
			if ($consomation > $limite) {
				$consomation1 = $limite;
				$consomation2 = $consomation - $limite;
				$valeur1 = $consomation1 * $pu;
				$valeur2 = $consomation2 * $pus;
				$totale = $valeur1 + $valeur2;
				$totaleHT = $totale + 1000;
				$surtaxe = ($totale * 5) / 100;
				$taxeCommunale = ($totale * 1) / 100;
				$audit = ($totale * 2) / 100;
				$totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
				if ($consomation > 10 && $pus != null) {
					$constva = ($consomation - 10) * $pus;
					$tva = ($constva * 20) / 100;
					$totaleFinale = $totaleHT + $totalTaxe + $tva;
				} else {
					$tva = 0;
					$totaleFinale = $totaleHT + $totalTaxe;
				}
			} else {
				$consomation1 = $consomation;
				$consomation2 = 0;
				$totale = $consomation1 * $pu;
				$totaleHT = $totale + 1000;
				$surtaxe = ($totale * 5) / 100;
				$taxeCommunale = ($totale * 1) / 100;
				$audit = ($totale * 2) / 100;
				$totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
				if ($consomation > 10 && $pus != null) {
					$constva = ($consomation - 10) * $pus;
					$tva = ($constva * 20) / 100;
					$totaleFinale = $totaleHT + $totalTaxe + $tva;
				} else {
					$tva = 0;
					$totaleFinale = $totaleHT + $totalTaxe;
				}
			}
		} else {
			$consomation1 = $consomation;
			$consomation2 = 0;
			$totale = $consomation1 * $pu;
			$totaleHT = $totale + 1000;
			$surtaxe = ($totale * 5) / 100;
			$taxeCommunale = ($totale * 1) / 100;
			$audit = ($totale * 2) / 100;
			$totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
			if ($consomation > 10 && $pus != null) {
				$constva = ($consomation - 10) * $pus;
				$tva = ($constva * 20) / 100;
				$totaleFinale = $totaleHT + $totalTaxe + $tva;
			} else {
				$tva = 0;
				$totaleFinale = $totaleHT + $totalTaxe;
			}
		}
		$sommeTotaleFinale += $totaleFinale;
	}


  // Récupérer le nombre de relevés payés (où la date de paiement est renseignée)
  $nombreRelevesPayes = $this->releveRepository
      ->createQueryBuilder('r')
      ->select('COUNT(r.id)')
      ->where('r.annee = :annee')
      ->andWhere('r.mois = :mois')
      ->andWhere('r.factureDatePaiement IS NOT NULL')
      ->setParameter('annee', $annee)
      ->setParameter('mois', $mois)
      ->getQuery()
      ->getSingleScalarResult();
	
$relevesPayes= $this->releveRepository
      ->createQueryBuilder('r')
      ->where('r.annee = :annee')
      ->andWhere('r.mois = :mois')
	  ->andWhere('r.factureDatePaiement IS NOT NULL')
      ->setParameter('annee', $annee)
      ->setParameter('mois', $mois)
      ->getQuery()
      ->getResult();

	$sommePayesFinale = 0;

	foreach ($relevesPayes as $releve) {
		$limite = $releve->getLimite();
		$consomation = $releve->getConsomation();
		$pu = $releve->getPu();
		$pus = $releve->getPus();

		if ($limite != null) {
			if ($consomation > $limite) {
				$consomation1 = $limite;
				$consomation2 = $consomation - $limite;
				$valeur1 = $consomation1 * $pu;
				$valeur2 = $consomation2 * $pus;
				$totale = $valeur1 + $valeur2;
				$totaleHT = $totale + 1000;
				$surtaxe = ($totale * 5) / 100;
				$taxeCommunale = ($totale * 1) / 100;
				$audit = ($totale * 2) / 100;
				$totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
				if ($consomation > 10 && $pus != null) {
					$constva = ($consomation - 10) * $pus;
					$tva = ($constva * 20) / 100;
					$totaleFinale = $totaleHT + $totalTaxe + $tva;
				} else {
					$tva = 0;
					$totaleFinale = $totaleHT + $totalTaxe;
				}
			} else {
				$consomation1 = $consomation;
				$consomation2 = 0;
				$totale = $consomation1 * $pu;
				$totaleHT = $totale + 1000;
				$surtaxe = ($totale * 5) / 100;
				$taxeCommunale = ($totale * 1) / 100;
				$audit = ($totale * 2) / 100;
				$totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
				if ($consomation > 10 && $pus != null) {
					$constva = ($consomation - 10) * $pus;
					$tva = ($constva * 20) / 100;
					$totaleFinale = $totaleHT + $totalTaxe + $tva;
				} else {
					$tva = 0;
					$totaleFinale = $totaleHT + $totalTaxe;
				}
			}
		} else {
			$consomation1 = $consomation;
			$consomation2 = 0;
			$totale = $consomation1 * $pu;
			$totaleHT = $totale + 1000;
			$surtaxe = ($totale * 5) / 100;
			$taxeCommunale = ($totale * 1) / 100;
			$audit = ($totale * 2) / 100;
			$totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
			if ($consomation > 10 && $pus != null) {
				$constva = ($consomation - 10) * $pus;
				$tva = ($constva * 20) / 100;
				$totaleFinale = $totaleHT + $totalTaxe + $tva;
			} else {
				$tva = 0;
				$totaleFinale = $totaleHT + $totalTaxe;
			}
		}
		$sommePayesFinale += $totaleFinale;
	}

  // Récupérer le nombre de relevés non payés (où la date de paiement est NULL)
  $nombreRelevesNonPayes = $this->releveRepository
      ->createQueryBuilder('r')
      ->select('COUNT(r.id)')
      ->where('r.annee = :annee')
      ->andWhere('r.mois = :mois')
      ->andWhere('r.factureDatePaiement IS NULL')
      ->setParameter('annee', $annee)
      ->setParameter('mois', $mois)
      ->getQuery()
      ->getSingleScalarResult();

	
	$relevesNPayes= $this->releveRepository
      ->createQueryBuilder('r')
      ->where('r.annee = :annee')
      ->andWhere('r.mois = :mois')
	  ->andWhere('r.factureDatePaiement IS NULL')
      ->setParameter('annee', $annee)
      ->setParameter('mois', $mois)
      ->getQuery()
      ->getResult();

	$sommeNPayesFinale = 0;

	foreach ($relevesNPayes as $releve) {
		$limite = $releve->getLimite();
		$consomation = $releve->getConsomation();
		$pu = $releve->getPu();
		$pus = $releve->getPus();

		if ($limite != null) {
			if ($consomation > $limite) {
				$consomation1 = $limite;
				$consomation2 = $consomation - $limite;
				$valeur1 = $consomation1 * $pu;
				$valeur2 = $consomation2 * $pus;
				$totale = $valeur1 + $valeur2;
				$totaleHT = $totale + 1000;
				$surtaxe = ($totale * 5) / 100;
				$taxeCommunale = ($totale * 1) / 100;
				$audit = ($totale * 2) / 100;
				$totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
				if ($consomation > 10 && $pus != null) {
					$constva = ($consomation - 10) * $pus;
					$tva = ($constva * 20) / 100;
					$totaleFinale = $totaleHT + $totalTaxe + $tva;
				} else {
					$tva = 0;
					$totaleFinale = $totaleHT + $totalTaxe;
				}
			} else {
				$consomation1 = $consomation;
				$consomation2 = 0;
				$totale = $consomation1 * $pu;
				$totaleHT = $totale + 1000;
				$surtaxe = ($totale * 5) / 100;
				$taxeCommunale = ($totale * 1) / 100;
				$audit = ($totale * 2) / 100;
				$totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
				if ($consomation > 10 && $pus != null) {
					$constva = ($consomation - 10) * $pus;
					$tva = ($constva * 20) / 100;
					$totaleFinale = $totaleHT + $totalTaxe + $tva;
				} else {
					$tva = 0;
					$totaleFinale = $totaleHT + $totalTaxe;
				}
			}
		} else {
			$consomation1 = $consomation;
			$consomation2 = 0;
			$totale = $consomation1 * $pu;
			$totaleHT = $totale + 1000;
			$surtaxe = ($totale * 5) / 100;
			$taxeCommunale = ($totale * 1) / 100;
			$audit = ($totale * 2) / 100;
			$totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
			if ($consomation > 10 && $pus != null) {
				$constva = ($consomation - 10) * $pus;
				$tva = ($constva * 20) / 100;
				$totaleFinale = $totaleHT + $totalTaxe + $tva;
			} else {
				$tva = 0;
				$totaleFinale = $totaleHT + $totalTaxe;
			}
		}
		$sommeNPayesFinale += $totaleFinale;
	}

// Vérification si le mois est sous forme de '1/2025' ou '01/2025' et extraction du mois
if (strpos($mois, '/') !== false) {
    // Si le mois est sous forme de '1/2025' ou '01/2025'
    $mois = explode('/', $mois)[0]; // Extrait le mois (par exemple, '1' ou '12')
}

// Vérifier si la valeur de $mois est valide (entre 1 et 12)
if (empty($mois) || !is_numeric($mois) || $mois < 1 || $mois > 12) {
    // Si $mois est invalide, définir une valeur par défaut (par exemple, 1 pour janvier)
    $mois = 1;
}

// Créer la requête avec les conditions de base
$relevesNonPayesQueryBuilder = $this->releveRepository
    ->createQueryBuilder('r')
    ->join('r.client', 'c')
    ->where('r.annee = :annee')
    ->andWhere('r.mois = :mois')
    ->andWhere('r.factureDatePaiement IS NULL')
    ->setParameter('annee', $annee)
    ->setParameter('mois', $mois);

// Vérifier si un terme de recherche est fourni
$searchQuery = $request->request->get('search');
if (!empty($searchQuery)) {  // Si le champ 'search' n'est pas vide
    // Appliquer la condition de recherche uniquement si un terme de recherche est présent
    $relevesNonPayesQueryBuilder->andWhere(
        $relevesNonPayesQueryBuilder->expr()->orX(
            'LOWER(c.nom) LIKE LOWER(:searchTerm)', // Recherche insensible à la casse
            'LOWER(c.prenom) LIKE LOWER(:searchTerm)', // Recherche insensible à la casse
            'LOWER(c.code) LIKE LOWER(:searchTerm)' // Recherche insensible à la casse
        )
    )
    ->setParameter('searchTerm', '%' . $searchQuery . '%');
}

// Exécuter la requête après avoir appliqué toutes les conditions
$relevesNonPayes = $relevesNonPayesQueryBuilder->getQuery()->getResult();

      $currentPage = $request->query->getInt('page', 1); // Récupérer le numéro de page actuel depuis la requête
      $perPage = 10; // Nombre d'éléments par page

      $releves = $paginator->paginate(
        $relevesNonPayes,
        $request->query->getInt('page', 1),
        10 // Nombre d'éléments par page
      );
      


  return $this->render('principale/index.html.twig', [
      'nombreTotalReleves' => $nombreTotalReleves,
      'nombreRelevesPayes' => $nombreRelevesPayes,
      'years' => $years,
      'selectedYear' => $annee,   
      'months'=>$months,
      'selectedMonth'=>$mois,      
      'months'=>$months,
      'nombreRelevesNonPayes' => $nombreRelevesNonPayes,
      'releve'=>$releves,
      'search'=>$searchQuery,
	  'sommeTotaleFinale'=>number_format($sommeTotaleFinale, 0, '', ' '),
	  'sommePayesFinale'=>number_format($sommePayesFinale, 0, '', ' '),
	  'sommeNPayesFinale'=>number_format($sommeNPayesFinale, 0, '', ' ')


  ]);
  

}
 
   
}
