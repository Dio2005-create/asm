<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\ClientBf;
use App\Entity\Releve;
use App\Entity\ReleveBf;
use App\Form\ClientType;
use App\Form\MonthYearFormType as FormMonthYearFormType;
use App\Form\ReleveBfType;
use App\Form\ReleveType;
use App\Repository\ClientBfRepository;
use App\Repository\ClientRepository;
use App\Repository\PaymentTrancheRepository;
use App\Repository\QuartierRepository;
use App\Repository\ReleveBfRepository;
use App\Repository\ReleveRepository;
use Symfony\Component\Intl\Intl;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use IntlDateFormatter;
use Knp\Component\Pager\PaginatorInterface;
use MonthYearFormType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Psr\Log\LoggerInterface;


/**
 * @Route("/admin")
 */
class AdminController extends AbstractController
{

    private $clientRepository;
    private $quartierRepository;
    private $entityManager;
    private $releveRepository;
    private $clientBfRepository;
    private $releveBfRepository;
	private $paymentTrancheRepository;



    function __construct(
        ClientRepository $clientRepository,
        QuartierRepository $quartierRepository,
        EntityManagerInterface $entityManager,
        ReleveRepository $releveRepository,
        ClientBfRepository $clientBfRepository,
        ReleveBfRepository $releveBfRepository,
		PaymentTrancheRepository $paymentTrancheRepository


    ) {
        $this->releveBfRepository=$releveBfRepository;
        $this->clientRepository=$clientRepository;
        $this->clientBfRepository=$clientBfRepository;
        $this->quartierRepository=$quartierRepository;
        $this->entityManager=$entityManager;
        $this->releveRepository=$releveRepository;
		$this->paymentTrancheRepository=$paymentTrancheRepository;
    }

    /**
     * @Route("/", name="admin")
     */
    public function index(Request $request, PaginatorInterface $paginator)
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
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

        return $this->render('admin/client.html.twig', [
            'pagination' => $pagination,
            'search'=>$searchQuery
        ]);
        
    }

     /**
     * @Route("/clients/bf", name="clients_bf")
     */
    public function clientsbf()
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_login');
        }
        $client = $this->clientBfRepository->findAll();
        return $this->render('admin/clientBf.html.twig',[
            'client'=>$client
        ]);
        
    }


      /**
     * @Route("/clients/{id}",name="client_admin")
     */
    public function client_admin($id, PaginatorInterface $paginator, Request $request){
        if (!$this->isGranted('ROLE_ADMIN')) {
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
        return $this->render('admin/see_client.html.twig',[
            'client'=>$client,
            'releves'=>$releve
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

        return $mois[ (int)$moisEnChiffre];
    }
/**
 * @Route("/export/client", name="export_client")
 */
public function export_client()
{
    
   
    $clients = $this->clientRepository->findAll();
    $spreadsheet = new Spreadsheet();
    
   
    $sheet = $spreadsheet->getActiveSheet();

    
    $headers = [
        'Code',
        'Nom',
        'Prenoms',
        'Quartier',
        'Adresse',
    ];

    // Ajoutez les en-têtes à la feuille Excel
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    $row = 2; // Commencez à partir de la ligne 2 car la première ligne contient les en-têtes

    foreach ($clients as $client) {
       
        $sheet->setCellValue('A' . $row, $client->getCode());
        $sheet->setCellValue('B' . $row, $client->getNom()); 
        $sheet->setCellValue('C' . $row, $client->getPrenom()); 
        $sheet->setCellValue('D' . $row, $client->getQuartier()->getNom()); 
        $sheet->setCellValue('E' . $row, $client->getAdresse()); 

       
        $row++;
    }

    

    // Créez un objet Writer (classe PhpSpreadsheet\Writer\Xlsx)
    $writer = new Xlsx($spreadsheet);

    // Configurez le chemin du fichier temporaire où le fichier Excel sera sauvegardé
    $tempFilePath = tempnam(sys_get_temp_dir(), 'liste_');


    $writer->save($tempFilePath);

    $response = new Response(file_get_contents($tempFilePath));
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $date = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
    $timestamp = $date->format('Y-m-d_H-i-s');
    
    $filename = 'liste_client_' . $timestamp . '.xlsx';
    
    $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
    $response->headers->set('Cache-Control', 'max-age=0');

    // Supprimez le fichier temporaire après l'avoir téléchargé
    unlink($tempFilePath);

    return $response;
}

/**
 * @Route("/export/bf/client", name="export_bf_client")
 */
public function export_bf_client()
{
    
   
    $clients = $this->clientBfRepository->findAll();
    $spreadsheet = new Spreadsheet();
    
   
    $sheet = $spreadsheet->getActiveSheet();

    
    $headers = [
        'Code',
        'Nom',
        'Prenoms',
        'Quartier',
        'Adresse',
    ];

    // Ajoutez les en-têtes à la feuille Excel
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    $row = 2; // Commencez à partir de la ligne 2 car la première ligne contient les en-têtes

    foreach ($clients as $client) {
       
        $sheet->setCellValue('A' . $row, $client->getCode());
        $sheet->setCellValue('B' . $row, $client->getNom()); 
        $sheet->setCellValue('C' . $row, $client->getPrenom()); 
        $sheet->setCellValue('D' . $row, $client->getQuartier()->getNom()); 
        $sheet->setCellValue('E' . $row, $client->getAdresse()); 

       
        $row++;
    }

    

    // Créez un objet Writer (classe PhpSpreadsheet\Writer\Xlsx)
    $writer = new Xlsx($spreadsheet);

    // Configurez le chemin du fichier temporaire où le fichier Excel sera sauvegardé
    $tempFilePath = tempnam(sys_get_temp_dir(), 'liste_');


    $writer->save($tempFilePath);

    $response = new Response(file_get_contents($tempFilePath));
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $date = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
    $timestamp = $date->format('Y-m-d_H-i-s');
    
    $filename = 'liste_client_bf_' . $timestamp . '.xlsx';
    
    $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
    $response->headers->set('Cache-Control', 'max-age=0');

    // Supprimez le fichier temporaire après l'avoir téléchargé
    unlink($tempFilePath);

    return $response;
}


/**
 * @Route("/generates/excel/{year}", name="generates_excel")
 */
public function generatesExcel($year)
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
 * @Route("/generer/excel/{year}/mois/{month}", name="generer_excel_mois")
 */
public function generer_excel_mois($year,$month)
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
 * @Route("/generates/excel/year/{year}/month/{month}/day/{day}", name="generates_excel_day")
 */
public function generatesExcelDay($year,$month,$day)
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
 * @Route("/generer/excel/releve/client/{id}", name="generer_excel_releve_patient")
 */
public function generatesExcelRelevePatient($id)
{
    
    $client = $this->clientRepository->find($id);
    $releves = $this->releveRepository->findBy(['client'=>$client]);
    $spreadsheet = new Spreadsheet();
   
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($client->getNom().' '.$client->getPrenom());
    
    $headers = [
        'Date',
        'Mois Relevé',
        'Index',
        'Consomation',
        'Montant'
      
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
                $totaleFinale = $totaleHT + $totalTaxe;

            }

        }
        $sheet->setCellValue('A' . $row, $releve->getDateReleve()); 
        $sheet->setCellValue('B' . $row, $moisv); 
        $sheet->setCellValue('C' . $row, $releve->getNouvelIndex()); 
        $sheet->setCellValue('D' . $row, $consomation); 
        $sheet->setCellValue('E' . $row, $totaleFinale);

        $row++;
    }

    

    // Créez un objet Writer (classe PhpSpreadsheet\Writer\Xlsx)
    $writer = new Xlsx($spreadsheet);

    // Configurez le chemin du fichier temporaire où le fichier Excel sera sauvegardé
    $tempFilePath = tempnam(sys_get_temp_dir(), 'detail_');

    // Sauvegardez le fichier Excel dans le chemin temporaire
    $writer->save($tempFilePath);

    // Créez une réponse HTTP pour le téléchargement du fichier Excel
    $response = new Response(file_get_contents($tempFilePath));
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $date = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
    $timestamp = $date->format('Y-m-d_H-i-s');
    
    $filename = 'detail_client_' . $timestamp . '.xlsx';
    
    $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
  
    $response->headers->set('Cache-Control', 'max-age=0');

    // Supprimez le fichier temporaire après l'avoir téléchargé
    unlink($tempFilePath);

    return $response;
}

/**
 * @Route("/export_bf_salaire/{year}/{month}",name="export_bf_salaire")
 */
public function export_bf_salaire($year,$month){
    $releves = $this->releveBfRepository->findByYearAndMonth($year, $month);
    $dates = $this->getDistinctDatesFromConsumptions($releves);
    $data = $this->organizeDataBf($releves, $year, $month, $dates);
    $totals = []; 

    foreach ($data['clients'] as $client => $clientData) {
        $total = array_sum($data['consommations'][$client]);
        $totals[$client] = $total;
    }
    $spreadsheet = new Spreadsheet();

    // Créez une feuille Excel et définissez son titre
    $sheet = $spreadsheet->getActiveSheet();
    $titre = $this->moisEnLettres($month).' '.$year;
    $sheet->setTitle($titre);

    // Remplissez la feuille Excel avec les données
    $row = 1;

    // Ajoutez l'en-tête des colonnes (code client, mois, consommations)
    $col = 1;
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Code Client');
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Nom Client');
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Consomation Totale');
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Total Versement'); 
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Taxe et redevance'); 
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Prix HT'); 
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Salaire'); 

    $row++;
    foreach ($data['clients'] as $client => $clientData) {
       
        $nom = $clientData->getNom().' '.$clientData->getPrenom();
        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $clientData->getCode());
        $sheet->setCellValueByColumnAndRow($col++, $row, $nom);
        $totaleHt = $totals[$client] * 3000;
        $totale = $totals[$client] * 2500;
        $surtaxe = ($totale * 5) / 100 ;
        $taxeCommunale = ($totale * 1) / 100 ;
        $audit = ($totale * 2) / 100 ;
        $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
        $totaleG = $totale - $totalTaxe;
        $salaire = $totals[$client] * 500;
        $sheet->setCellValueByColumnAndRow($col++, $row, $totals[$client]);
        $sheet->setCellValueByColumnAndRow($col++, $row, $totaleHt);
        $sheet->setCellValueByColumnAndRow($col++, $row, $totalTaxe);
        $sheet->setCellValueByColumnAndRow($col++, $row, $totaleG);
        $sheet->setCellValueByColumnAndRow($col++, $row, $salaire);
        $row++;
    }
     $writer = new Xlsx($spreadsheet);
     $tempFilePath = tempnam(sys_get_temp_dir(), 'Borne_');
     $writer->save($tempFilePath);
    
     $response = new Response(file_get_contents($tempFilePath));
     $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
     $date = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
     $timestamp = $date->format('Y-m-d_H-i-s');
     
     $filename = 'Borne_fontaine_salaire_' . $timestamp . '.xlsx';
     
     $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
     $response->headers->set('Cache-Control', 'max-age=0');
    
     return $response;


}



/**
 * @Route("/export_excel_bf/{year}/{month}",name="export_excel_bf")
 */
public function export_excel_bf($year,$month){
    $releves = $this->releveBfRepository->findByYearAndMonth($year, $month);
    $dates = $this->getDistinctDatesFromConsumptions($releves);
    $data = $this->organizeDataBf($releves, $year, $month, $dates);
    $totals = []; 

    foreach ($data['clients'] as $client => $clientData) {
        $total = array_sum($data['consommations'][$client]);
        $totals[$client] = $total;
    }
    $spreadsheet = new Spreadsheet();

    // Créez une feuille Excel et définissez son titre
    $sheet = $spreadsheet->getActiveSheet();
    $titre = $this->moisEnLettres($month).' '.$year;
    $sheet->setTitle($titre);

    // Remplissez la feuille Excel avec les données
    $row = 1;

    // Ajoutez l'en-tête des colonnes (code client, mois, consommations)
    $col = 1;
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Code Client');
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Nom Client');
    foreach ($data['dates'] as $mois) {
        $sheet->setCellValueByColumnAndRow($col++, $row, $mois);
    } 
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Total'); 
    $row++;
    foreach ($data['clients'] as $client => $clientData) {
       
        $nom = $clientData->getNom().' '.$clientData->getPrenom();
        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $clientData->getCode());
        $sheet->setCellValueByColumnAndRow($col++, $row, $nom);
        foreach ($data['consommations'][$client] as $consommation) {
            $sheet->setCellValueByColumnAndRow($col++, $row, $consommation);
        }
        $sheet->setCellValueByColumnAndRow($col++, $row, $totals[$client]);
        $row++;
    }
     $writer = new Xlsx($spreadsheet);
     $tempFilePath = tempnam(sys_get_temp_dir(), 'livre_');
     $writer->save($tempFilePath);
    
     $response = new Response(file_get_contents($tempFilePath));
     $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
     $date = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
     $timestamp = $date->format('Y-m-d_H-i-s');
     
     $filename = 'livre_bf_' . $timestamp . '.xlsx';
     
     $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
   
     $response->headers->set('Cache-Control', 'max-age=0');
    
     return $response;


}



   /**
 * @Route("/export-excel/{year}", name="export_excel")
 */
public function exportExcel($year): Response
{
   
    $selectedYear = (int)$year;
    $releves = $this->releveRepository->findByYear($selectedYear);
    $data = $this->organizeData($releves);

 
$totals = []; 

foreach ($data['clients'] as $client => $clientData) {
    $total = array_sum($data['consommations'][$client]);
    $totals[$client] = $total;
}


    // Créez un objet Spreadsheet (classe PHPOffice\PhpSpreadsheet\Spreadsheet)
    $spreadsheet = new Spreadsheet();

    // Créez une feuille Excel et définissez son titre
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Livre des clients');

    // Remplissez la feuille Excel avec les données
    $row = 1;

    // Ajoutez l'en-tête des colonnes (code client, mois, consommations)
    $col = 1;
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Code Client');
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Nom Client');
    foreach ($data['mois'] as $mois) {
        $sheet->setCellValueByColumnAndRow($col++, $row, $mois);
    }
    $sheet->setCellValueByColumnAndRow($col++, $row, 'Total'); 
    $row++;

    foreach ($data['clients'] as $client => $clientData) {
       
        $nom = $clientData->getNom().' '.$clientData->getPrenom();
        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $clientData->getCode());
        $sheet->setCellValueByColumnAndRow($col++, $row, $nom);
        foreach ($data['consommations'][$client] as $consommation) {
            $sheet->setCellValueByColumnAndRow($col++, $row, $consommation);
        }
        $sheet->setCellValueByColumnAndRow($col++, $row, $totals[$client]);
        $row++;
    }
     $writer = new Xlsx($spreadsheet);
     $tempFilePath = tempnam(sys_get_temp_dir(), 'livre_');
     $writer->save($tempFilePath);
    
     $response = new Response(file_get_contents($tempFilePath));
     $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
     $date = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
     $timestamp = $date->format('Y-m-d_H-i-s');
     
     $filename = 'livre_client_' . $timestamp . '.xlsx';
     
     $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
   
     $response->headers->set('Cache-Control', 'max-age=0');
    
     return $response;
}



 /**
 * @Route("/livret/client/bf", name="livret_client_bf")
 */
public function getLivretClientBf(Request $request)
{
    $years = $this->releveBfRepository->findUniqueYears();
    $currentYear = date('Y');
    $currentMonth = date('m');
    $months = [
        'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
    ];
    $selectedYear = $request->request->get('year', $currentYear);
    $selectedMonth = $request->request->get('month', $currentMonth);

    // Obtenir les consommations pour le mois et l'année sélectionnés
    $releves = $this->releveBfRepository->findByYearAndMonth($selectedYear, $selectedMonth);

    // Obtenir les dates distinctes à partir des données de consommation
    $dates = $this->getDistinctDatesFromConsumptions($releves);

    // Organiser les données en fonction des dates
    $organizedData = $this->organizeDataBf($releves, $selectedYear, $selectedMonth, $dates);

    return $this->render('admin/livret_client_bf.html.twig', [
        'years' => $years,
        'months' => $months,
        'selectedYear' => $selectedYear,
        'selectedMonth' => $selectedMonth,
        'data' => $organizedData,
        'dates' => $dates,
    ]);
}

// Méthode pour obtenir les dates distinctes à partir des données de consommation
private function getDistinctDatesFromConsumptions($releves)
{
    $distinctDates = [];

    foreach ($releves as $releve) {
        $date = $releve->getDateReleve()->format('j');
        if (!in_array($date, $distinctDates)) {
            $distinctDates[] = $date;
        }
    }

    return $distinctDates;
}
private function organizeDataBf($releves, $selectedYear, $selectedMonth, $dates)
{
    $data = [
        'clients' => [], // Les clients uniques
        'dates' => $dates, // Dates avec des données de consommation
        'consommations' => [], // Les consommations par client et par date
    ];

    // Créer un tableau associatif pour stocker les consommations par client et date
    $consommationsData = [];

    // Initialiser le tableau des consommations avec des valeurs à zéro pour chaque client
    foreach ($releves as $releve) {
        $client = $releve->getClient();
        $clientId = $client->getId();

        if (!in_array($client, $data['clients'])) {
            $data['clients'][] = $client;
        }

        if (!isset($consommationsData[$clientId])) {
            $consommationsData[$clientId] = [];
        }
    }

    // Remplir le tableau des consommations en fonction des relevés
    foreach ($releves as $releve) {
        $client = $releve->getClient();
        $date = $releve->getDateReleve()->format('j');

        if (in_array($date, $dates)) {
            $clientId = $client->getId();
            if ($releve->getConsomation() != null) {
                $consommationsData[$clientId][$date] = $releve->getConsomation();
            } else {
                $consommationsData[$clientId][$date] = 0;
            }
        }
    }

    // Organiser les données en fonction des clients et des dates
    foreach ($data['clients'] as $client) {
        $consommationsRow = [];

        foreach ($dates as $date) {
            $clientId = $client->getId();
          
            if (isset($consommationsData[$clientId][$date])) {
                $consommationsRow[] = $consommationsData[$clientId][$date];
            } else {
                // Ajoutez une valeur par défaut (0) lorsque l'indice n'existe pas
                $consommationsRow[] = 0;
            }
        }
        
        $data['consommations'][] = $consommationsRow;
        
    }

    return $data;
}






    /**
     * @Route("/livret/client", name="livret_client")
     */
    public function getLivretClient(Request $request)
    {
        
          
          $years = $this->releveRepository->findUniqueYears();
          $currentYear = date('Y');
          $selectedYear = $request->request->get('year', $currentYear);
          $releves = $this->releveRepository->findByYear($selectedYear);
        $data = $this->organizeData($releves);
        return $this->render('admin/livret_client.html.twig', [
            'data' => $data,
            'years' => $years,
            'selectedYear' => $selectedYear,
        ]);
    }

private function organizeData($releves)
{
    // Initialiser un tableau pour stocker les données
    $data = [
        'clients' => [], // Les clients uniques
        'mois' => [], // Les mois triés en français
        'consommations' => [], // Les totaux de consommation par client et par mois
    ];

    // Triez les relevés par mois (janvier en premier)
    usort($releves, function ($a, $b) {
        $dateA = $a->getDateReleve();
        $dateB = $b->getDateReleve();
        if ($dateA == $dateB) {
            return 0;
        }
        return ($dateA < $dateB) ? -1 : 1;
    });

    // Remplir les clients uniques
    foreach ($releves as $releve) {
        $client = $releve->getClient();
        if (!in_array($client, $data['clients'])) {
            $data['clients'][] = $client;
        }
    }

    // Remplir les mois triés en français
    foreach ($releves as $releve) {
        $mois = $releve->getDateReleve()->format('F'); // Obtenez le nom du mois en anglais
        $moisFrancais = $this->convertToFrenchMonth($mois);
        if (!in_array($moisFrancais, $data['mois'])) {
            $data['mois'][] = $moisFrancais;
        }
    }

    // Trier les mois par leur position dans l'année (janvier en premier) en français
    usort($data['mois'], function ($a, $b) {
        $monthsInYear = [
            "janvier", "février", "mars", "avril", "mai", "juin",
            "juillet", "août", "septembre", "octobre", "novembre", "décembre"
        ];
        return array_search($a, $monthsInYear) - array_search($b, $monthsInYear);
    });

    // Initialiser le tableau des consommations avec des valeurs à -
    foreach ($data['clients'] as $client) {
        $consommationRow = [];
        foreach ($data['mois'] as $mois) {
            $consommationRow[] = '-';
        }
        $data['consommations'][] = $consommationRow;
    }

    // Ajout des consommations
    foreach ($releves as $releve) {
        $clientIndex = array_search($releve->getClient(), $data['clients']);
        $mois = $releve->getDateReleve()->format('F'); // Obtenez le nom du mois en anglais
        $moisFrancais = $this->convertToFrenchMonth($mois);
        $moisIndex = array_search($moisFrancais, $data['mois']);

        $consommation = $releve->getConsomation();

        // Validation de la valeur de consommation
        if (!is_numeric($consommation)) {
            // Inclure la valeur problématique dans le message d'exception
            throw new \InvalidArgumentException('La consommation doit être un nombre. Valeur trouvée : ' . var_export($consommation, true));
        }

        // Si la consommation est trouvée, mettre à jour la valeur de la consommation
        $data['consommations'][$clientIndex][$moisIndex] = floatval($consommation);
    }

    return $data;
}


private function convertToFrenchMonth($englishMonth)
{
    $monthTranslations = [
        'January' => 'janvier',
        'February' => 'février',
        'March' => 'mars',
        'April' => 'avril',
        'May' => 'mai',
        'June' => 'juin',
        'July' => 'juillet',
        'August' => 'août',
        'September' => 'septembre',
        'October' => 'octobre',
        'November' => 'novembre',
        'December' => 'décembre',
    ];

    return $monthTranslations[$englishMonth];
}


    

     /**
     * @Route ("/coupure/client",name="coupure_client")
     *
     */
    public function payer_releve(Request $request)
    {
        $id = $request->request->get('id');

        if ($id != null){
           
           $client = $this->clientRepository->find($id);
           $client->setCoupure(true);
           $this->entityManager->persist($client);
           $this->entityManager->flush();
           $message = 'La coupure est effectué';
           $this->addFlash('success', $message);
        }

        return new JsonResponse(true);
    }

         /**
     * @Route ("/remise/client",name="remise_client")
     *
     */
    public function remise_client(Request $request)
    {
        $id = $request->request->get('id');

        if ($id != null){
           
           $client = $this->clientRepository->find($id);
           $client->setCoupure(null);
           $this->entityManager->persist($client);
           $this->entityManager->flush();
           $message = 'La coupure est effectué';
           $this->addFlash('success', $message);
        }

        return new JsonResponse(true);
    }


    /**
     * @Route("/add/form/edit/client",name="add_form_edit_client")
     */
/**
 * @Route("/add/form/edit_client", name="add_form_edit_client")
 */
public function add_form_edit_client(Request $request)
{
    try {
        $client = null;
        $clientId = $request->request->get('id');
        $type = $request->request->get('type');

        // Récupérer le client en fonction du type
        if ($type == "bf") {
            $client = $this->clientBfRepository->find($clientId);
        } else {
            $client = $this->clientRepository->find($clientId);
        }

        // Si le client n'est pas trouvé
        if (!$client) {
            throw $this->createNotFoundException("Client not found");
        }

        // Création du formulaire
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Traitez les données si le formulaire est valide (enregistrement en base, etc.)
            $this->entityManager->flush();
        }

        // Génération de la vue du formulaire
        $response = $this->renderView('admin/_form_edit_client.html.twig', [
            'new' => false,
            'form' => $form->createView(),
            'eventData' => $client,
            'type' => $type
        ]);

        return new JsonResponse(['form_html' => $response]);
        
    } catch (\Exception $e) {
        // Capturer l'erreur et la renvoyer dans la réponse
        return new JsonResponse(['error' => 'An error occurred: ' . $e->getMessage()], 500);
    }
}


/**
 * @Route("/add/form_payer", name="add_form_payer")
 */
public function add_form_payer(Request $request)
{
    $id = $request->request->get('id');
    $response = $this->renderView('admin/_form_payer.html.twig', [
        'id' => $id
    ]);

    return new JsonResponse(['form_html' => $response]);
}

/**
 * @Route("/add/form/client", name="add_form_client")
 * @param Request $request
 * @return Response
 */
public function add_form_client(Request $request)
{
    // Initialisation d'un nouveau client vide
    $client = new Client();  // Assurez-vous que `Client` est l'entité associée au formulaire

    $type = $request->request->get('type');
    $form = $this->createForm(ClientType::class, $client);

    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
        // Traitez les données si le formulaire est valide
        $this->entityManager->persist($client);
        $this->entityManager->flush();
    }

    // Génération de la vue du formulaire
    $response = $this->renderView('admin/_form_client.html.twig', [
        'new' => true,
        'form' => $form->createView(),
        'type' => $type
    ]);

    return new JsonResponse(['form_html' => $response]);
}

/**
 * @Route("/register/edit/client", name="register_edit_client")
 */
public function register_edit_client(Request $request)
{
    $clientData = $request->request->get('client');
    $id = $clientData['id'];
    $type = $request->request->get('type');

    // Récupérer le client en fonction du type
    $client = ($type == "bf") ? $this->clientBfRepository->find($id) : $this->clientRepository->find($id);

    // Si le client n'existe pas
    if (!$client) {
        throw $this->createNotFoundException("Client not found");
    }

    // Mettre à jour les informations du client
    $client->setNom($clientData['nom']);
    $client->setPrenom($clientData['prenom']);
    $client->setAdresse($clientData['adresse']);
    $client->setAudit($clientData['audit']);
    $client->setIsSpecific($clientData['isSpecific']);
    
    // Récupérer le quartier
    $quartier = $this->quartierRepository->find($clientData['quartier']);
    $client->setQuartier($quartier);

    // Générer le nouveau code
    $code = $client->getCode();
    $explode = explode('/', $code);
    $newCode = (count($explode) == 2) ? $quartier->getCode() . '/' . $explode[1] : $quartier->getCode() . '/' . $explode[2];
    $client->setCode($newCode);

    // Sauvegarder les modifications
    $this->entityManager->persist($client);
    $this->entityManager->flush();

    // Redirection après modification
    if ($type == "bf") {
        return $this->redirectToRoute('clients_bf');
    } else {
        return $this->redirectToRoute('admin');
    }
}

    /**
     * @Route("/register/client", name="register_client")
     */
    public function register_client(Request $request)
    {
       $cliente = $request->request->get('client');
		
       $quartier = $cliente['quartier'];
       $nom = $cliente['nom'];
       $prenom = $cliente['prenom'];
       $adresse = $cliente['adresse'];
       $audit = $cliente['audit'];
	   $specifique = $cliente['isSpecific'];
       $quartier = $this->quartierRepository->find($quartier);
       $type = $request->request->get('type');
       if ($type == "bf") {
           $client = $this->clientBfRepository->findOneBy([],['id'=>'desc']);
       }else{
           $client = $this->clientRepository->findOneBy([],['id'=>'desc']);
       }
       $codes = $quartier->getCode();
       if(!$client){
        $code = $codes.'/'.'0001';
       }else{
        $code = $client->getCode();
        $explode = explode('/',$code);
        $nextId = str_pad((int)$client->getId() + 1, 4, '0', STR_PAD_LEFT);
        $code = $codes.'/'.$nextId;
       }
       if ($type =="bf") {
        $clients = new ClientBf();
       }else{
        $clients = new Client();
       }
       $clients->setCode($code);
       $clients->setQuartier($quartier);
       $clients->setNom($nom);
       $clients->setPrenom($prenom);
	   $clients->setAudit($audit);
	   $clients->setIsSpecific($specifique);
       $clients->setAdresse($adresse);
       $this->entityManager->persist($clients);
       $this->entityManager->flush();
       if ($type =="bf") {
        return $this->redirectToRoute('clients_bf');
       }else{
        return $this->redirectToRoute('admin');

       }
      
    }
/**
 * @Route("/releve/bf",name="releve_bf")
 */
public function releve_bf(Request $request)
{
    if (!$this->isGranted('ROLE_ADMIN')) {
        return $this->redirectToRoute('app_login');
    }
    $releve = $this->releveBfRepository->findAll();
    $client = [];
    $form = $this->createForm(ReleveBfType::class,$client);
    return $this->render('admin/releve_bf.html.twig',[
        'form' => $form->createView(),
        'releve'=>$releve,
    ]);

}
/**
* @Route("/releve",name="releve_admin")
*/
public function releve_admin(Request $request, PaginatorInterface $paginator)
 {
    if (!$this->isGranted('ROLE_ADMIN')) {
     return $this->redirectToRoute('app_login');
    }
    $years = $this->releveRepository->findUniqueYears();
    $currentYear = date('Y');
    $currentMonth = date('m');
    $selectedYear = $request->request->get('year', $currentYear);
    $quartier = $this->quartierRepository->findAll();
    $months = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
    ];
    $selectedMonth = $request->request->get('month',$currentMonth);
    $selectedQuartier = $request->request->get('quartier');
    if ($request->query->has('selectedYear')) {
    $selectedYear = $request->query->get('selectedYear');
    $selectedMonth = $request->query->get('selectedMonth');
    $selectedQuartier = $request->query->get('selectedQuartier');
    }
    if ($selectedQuartier ) {
    $releves = $this->releveRepository->findByYearQuartierAndMonth($selectedYear, $selectedQuartier, $selectedMonth);
    } else {
    $releves = $this->releveRepository->findByMonthYear($selectedYear,$selectedMonth);
    }
	

    $client = [];
    $form = $this->createForm(ReleveType::class,$client);
    return $this->render('admin/releve.html.twig',[
    'form' => $form->createView(),
    'releve'=>$releves,
    'years'=>$years,
    'quartiers'=>$quartier,
    'months'=>$months,
    'selectedYear' => $selectedYear,
    'selectedMonth' => $selectedMonth,
    'selectedQuartier' => $selectedQuartier,

    ]);

 }
	
	
	/**
 * @Route("/export/releves", name="export_admin_releves_to_excel")
 */
public function exportReleves(Request $request)
{
    $selectedYear = $request->request->get('year', date('Y'));
    $selectedMonth = $request->request->get('month', date('m'));
    $quartiers = $this->quartierRepository->findAll(); // Récupérer tous les quartiers

    $spreadsheet = new Spreadsheet();

    foreach ($quartiers as $index => $quartier) {
        $sheet = ($index === 0) ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
        $sheet->setTitle($quartier->getNom());

        // En-têtes
        $headers = [
        'Date Releve',
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

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $releves = $this->releveRepository->findByYearQuartierAndMonth(
            $selectedYear,
            $quartier->getId(),
            $selectedMonth
        );

        $row = 2; // Commencez après les en-têtes
        foreach ($releves as $releve) {
        $mois = $releve->getMois();
        $annee = $releve->getAnnee();
        $moisv = $this->moisEnLettres($mois) . '-' . $annee;
        $nom = $releve->getClient()->getNom() . ' ' . $releve->getClient()->getPrenom();
        $limite = $releve->getLimite();
        $consommation = $releve->getConsomation();
        $pu = $releve->getPu();
        $pus = $releve->getPus();

        if ($limite != null) {
            if ($consommation > $limite) {
                $consommation1 = $limite;
                $consommation2 = $consommation - $limite;
                $valeur1 = $consommation1 * $pu;
                $valeur2 = $consommation2 * $pus;
                $totale = $valeur1 + $valeur2;
                $totaleHT = $totale + 1000;
                $surtaxe = ($totale * 5) / 100;
                $taxeCommunale = ($totale * 1) / 100;
                $audit = ($totale * 2) / 100;
                $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                if ($consommation > 10 && $pus != null) {
                    $constva = ($consommation - 10) * $pus;
                    $tva = ($constva * 20) / 100;
                    $totaleFinale = $totaleHT + $totalTaxe + $tva;
                } else {
                    $tva = 0;
                    $totaleFinale = $totaleHT + $totalTaxe;
                }
            } else {
                $consommation1 = $consommation;
                $consommation2 = 0;
                $totale = $consommation1 * $pu;
                $totaleHT = $totale + 1000;
                $surtaxe = ($totale * 5) / 100;
                $taxeCommunale = ($totale * 1) / 100;
                $audit = ($totale * 2) / 100;
                $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
                if ($consommation > 10 && $pus != null) {
                    $constva = ($consommation - 10) * $pus;
                    $tva = ($constva * 20) / 100;
                    $totaleFinale = $totaleHT + $totalTaxe + $tva;
                } else {
                    $tva = 0;
                    $totaleFinale = $totaleHT + $totalTaxe;
                }
            }
        } else {
            $consommation1 = $consommation;
            $consommation2 = 0;
            $totale = $consommation1 * $pu;
            $totaleHT = $totale + 1000;
            $surtaxe = ($totale * 5) / 100;
            $taxeCommunale = ($totale * 1) / 100;
            $audit = ($totale * 2) / 100;
            $totalTaxe = $surtaxe + $taxeCommunale + $audit + $audit;
            if ($consommation > 10 && $pus != null) {
                $constva = ($consommation - 10) * $pus;
                $tva = ($constva * 20) / 100;
                $totaleFinale = $totaleHT + $totalTaxe + $tva;
            } else {
                $tva = 0;
                $totaleFinale = $totaleHT + $totalTaxe;
            }
        }
       
        $sheet->setCellValue('A' . $row, $releve->getDateReleve()->format('d/m/Y'));
        $sheet->setCellValue('B' . $row, $moisv);
        $sheet->setCellValue('C' . $row, $releve->getClient()->getCode());
        $sheet->setCellValue('D' . $row, $nom);
        $sheet->setCellValue('E' . $row, $releve->getClient()->getQuartier()->getNom());
        $sheet->setCellValue('F' . $row, $consommation);
        $sheet->setCellValue('G' . $row, $consommation1);
        $sheet->setCellValue('H' . $row, $pu);
        $sheet->setCellValue('I' . $row, $consommation2);
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
    }

    $writer = new Xlsx($spreadsheet);
    $tempFilePath = tempnam(sys_get_temp_dir(), 'releves_');
    $writer->save($tempFilePath);

    $response = new Response(file_get_contents($tempFilePath));
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $date = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
    $timestamp = $date->format('Y-m-d_H-i-s');
    $filename = 'export_releves_' . $timestamp . '.xlsx';
    $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
    $response->headers->set('Cache-Control', 'max-age=0');

    unlink($tempFilePath);

    return $response;
}
 
 
	
  /**
   * @Route("/register/payments/facture",name="register_payments")
   */
  public function register_payments(Request $request)
  {
    $id = $request->request->get('id');
    $date = $request->request->get('date');
    $releve = $this->releveRepository->find($id);
    $date = new DateTime($date);
    $releve->setFactureDatePaiement($date);
    $releve->setPayer(true);
    $this->entityManager->persist($releve);
    $this->entityManager->flush();
    return $this->redirectToRoute('client_admin',[
        'id'=>$releve->getClient()->getId()
    ]);

  }
  /**
   * @Route("/register_releve",name="register_releve")
   */
    public function register_releve(Request $request)
    {
        $releve = $request->get('releve');
        $client = $releve['client'];
        $client = $this->clientRepository->find($client);
        $dateReleve = $request->request->get('dateReleve');
        $month = $request->request->get('months');
        $month = explode("-",$month);
        $dateAncienIndex = $request->request->get('ancienReleve');
        $dates = DateTime::createFromFormat('d/m/Y', $dateAncienIndex);
        $anciensIndex = $request->request->get('anciensIndex');
        $nouvelleIndex = $request->request->get('nouvelleIndex');
        $consommations = $request->request->get('consommations');
        $limite=$request->request->get('limite');
        $pu=$request->request->get('pu');
        $pus=$request->request->get('pus');
        $releves = new Releve();
        $date = new DateTime($dateReleve);
        $releves->setDateReleve($date);
        if ($dates != null) {
            $releves->setDateAncienIndex($dates);
        }
        $releves->setMois($month[1]);
        $releves->setAnnee($month[0]);
        $releves->setClient($client);
		if($anciensIndex == null){
			$releves->setAncienIndex("");
		}else{
			$releves->setAncienIndex($anciensIndex);
		}
		
		if($nouvelleIndex == null){
			$releves->setNouvelIndex("");
		}else{
			$releves->setNouvelIndex($nouvelleIndex);
		}
		
        if($consommations == null){
		  $releves->setConsomation(0);
		}else{
			$releves->setConsomation($consommations);
		}
        
        $releves->setLimite($limite);
        $releves->setPu($pu);
        $releves->setPus($pus);
        $this->entityManager->persist($releves);
        $this->entityManager->flush();
        return new JsonResponse(true);

        
    }

    /**
   * @Route("/register_releve_bf",name="register_releve_bf")
   */
  public function register_releve_bf(Request $request)
  {
      $releve = $request->get('releve_bf');
      $client = $releve['client'];
      $client = $this->clientBfRepository->find($client);
      $dateReleve = $request->request->get('dateReleve');
      $month = $request->request->get('months');
      $month = explode("-",$month);
      $dateAncienIndex = $request->request->get('ancienReleve');
      $anciensIndex = $request->request->get('anciensIndex');
      $nouvelleIndex = $request->request->get('nouvelleIndex');
      $consommations = $request->request->get('consommations');
      $pu=$request->request->get('pu');
      $releves = new ReleveBf();
      $date = new DateTime($dateReleve);
      $releves->setDateReleve($date);
      if( $dateAncienIndex != null){
         $dates = DateTime::createFromFormat('d/m/Y', $dateAncienIndex);
         $releves->setDateAncienIndex($dates);
      }
      
      $releves->setMois($month[1]);
      $releves->setAnnee($month[0]);
      $releves->setClient($client);
      $releves->setAncienIndex($anciensIndex);
      $releves->setNouvelIndex($nouvelleIndex);
      $releves->setConsomation($consommations);

      $releves->setPu($pu);
      $releves->setFactureDatePaiement(new \DateTime('now', new \DateTimeZone('Indian/Antananarivo')));
      $this->entityManager->persist($releves);
      $this->entityManager->flush();
      return $this->redirectToRoute('releve_bf');

      
  }

    /**
   * @Route("/register/edit/releve",name="register_edit_releve")
   */
  public function register_edit_releve(Request $request)
  {
      $releve = $request->request->get('releve');
      $client = $releve['client'];
      $id = $releve['id'];
      $client = $this->clientRepository->find($client);
      $dateReleve = $request->request->get('dateReleve');
      $month = $request->request->get('month');
      $month = explode("-",$month);
      $dateAncienIndex = $request->request->get('ancienReleve');
      $rdv_date = str_replace("/", "-", $dateAncienIndex);
      $dates = new \DateTime($rdv_date);
      $anciensIndex = $request->request->get('anciensIndex');
      $nouvelleIndex = $request->request->get('nouvelleIndex');
      $consommations = $request->request->get('consommations');
      $limite=$request->request->get('limite');
      $pu=$request->request->get('pu');
      $pus=$request->request->get('pus');
      $releves = $this->releveRepository->find($id);
      $date = new DateTime($dateReleve);
      $releves->setDateReleve($date);
      $releves->setDateAncienIndex($dates);
      $releves->setMois($month[1]);
      $releves->setAnnee($month[0]);
      $releves->setClient($client);
      $releves->setAncienIndex($anciensIndex);
      $releves->setNouvelIndex($nouvelleIndex);
      $releves->setConsomation($consommations);
      $releves->setLimite($limite);
      $releves->setPu($pu);
      $releves->setPus($pus);
      $this->entityManager->persist($releves);
      $this->entityManager->flush();
      return $this->redirectToRoute('releve_admin');

      
  }
    /**
     * @Route("/generer/pdf",name="generer_pdf")
     * 
     */
    public function generer_pdf(Request $request)
    {
    
        $selectedIds = $request->request->get('ids',[]);
        $selectedData = $this->getDataForSelectedIds($selectedIds);
        
        return $this->render('admin/pdf.html.twig', [
            'selectedData' => $selectedData,
        ]);
        
    }
   private function getDataForSelectedIds($selectedIds)
	{
		$selectedIds = array_map('intval', $selectedIds);
		$selectedData = $this->releveRepository->findBy(['id' => $selectedIds]);

		return $selectedData;
	}

  /**
   * @Route("/add/form/releve",name="add_form_releve")
   */
  public function add_form_releve(Request $request)
  {

    $client = [];
    $client['id']= $request->request->get('id');
    $releve = $this->releveRepository->find($client['id']);
    $client['client']= $releve->getClient();
    $dateReleve = $releve->getDateReleve()->format('Y-m-d');
    $month = $releve->getDateReleve()->format('Y-m');
    $ancienIndex = $releve->getAncienIndex();
    $nouvelIndex = $releve->getNouvelIndex();
    $consomation = $releve->getConsomation();
    $limite = $releve->getLimite();
    $pu = $releve->getPu();
    $pus = $releve->getPus();
    if($consomation > $limite){
        $qte1 = $limite;
        $qte2 = $consomation - $limite;
        $montant1 = $qte1 * $pu;
        $montant2 = $qte2 * $pus;
    }else{
        $qte1 = $consomation;
        $qte2 = 0;
        $montant1 = $qte1 * $pu;
        $montant2 = 0;
    }
    $dateAncien = $releve->getDateAncienIndex()->format('d/m/Y');
    $ancienReleve = $this->releveRepository->findOneBy(['client'=>$releve->getClient()->getId(),'nouvelIndex'=>$ancienIndex]);
   if($ancienReleve != null){
    $MoisAncien = $ancienReleve->getMois() .'/'. $ancienReleve->getAnnee();
    $indexAncien = $ancienReleve->getAncienIndex();
    $consomationAncien = $ancienReleve->getConsomation();
   }else{
    $MoisAncien = "";
    $indexAncien = 0;
    $consomationAncien = $releve->getAncienIndex();
   }
    
    $form = $this->createForm(ReleveType::class,$client);
    $response = $this->renderView('admin/_form_releve.html.twig', [
        'new' => true,
        'form' => $form->createView(),
        'dateReleve'=>$dateReleve,
        'month'=>$month,
        'ancienIndex'=>$ancienIndex,
        'nouvelIndex'=>$nouvelIndex,
        'consomation'=>$consomation,
        'limite'=>$limite,
        'pu'=>$pu,
        'pus'=>$pus,
        'quantite1'=>$qte1,
        'quantite2'=>$qte2,
        'montant1'=>$montant1,
        'montant2'=>$montant2,
        'dateAncien'=>$dateAncien,
        "moisAncien"=>$MoisAncien,
        "indexAncien"=>$indexAncien,
        "consomationAncien"=>$consomationAncien

    ]);
     $form->handleRequest($request);
     return new JsonResponse(['form_html' => $response]);
  }

    /**
     * @Route("/relevebf_check",name="releve_bf_check")
     */
    public function releve_check_bf(Request $request)
    {
        $id = $request->request->get('id');
        $client = $this->clientBfRepository->find($id);
        $releve = $this->releveBfRepository->findOneBy(['client'=>$client],['id'=>'desc']);
        $nom = $client->getNom().' '.$client->getPrenom();
        if( $releve != null){
            $date = $releve->getDateReleve()->format('d/m/Y');
            $mois = $releve->getMois();
            $anne = $releve->getAnnee();
            $ancienIndex = $releve->getAncienIndex();
            $nouvelIndex = $releve->getNouvelIndex();
            $consomation = $releve->getConsomation();
            $mois = $mois .'/'.$anne;

        }else{
            $date = '';
            $mois = '';
            $ancienIndex = '';
            $nouvelIndex = '';
            $consomation = '';
          

        }
       
        $donne = ['nom'=>$nom,'date'=>$date,'mois'=>$mois,'ancienIndex'=>$ancienIndex,'nouvelIndex'=>$nouvelIndex,'consommation'=>$consomation];

        return new JsonResponse($donne);
        
    }


    /**
	 * @Route("/releve_check",name="releve_check")
	 */
	public function releve_check(Request $request)
	{
		$id = $request->request->get('id');
		$client = $this->clientRepository->find($id);
		$releve = $this->releveRepository->findOneBy(['client'=>$client],['id'=>'desc']);

		$nom = $client ? $client->getNom().' '.$client->getPrenom() : '';
		$date = $releve ? $releve->getDateReleve()->format('d/m/Y') : '';
		$mois = $releve ? $releve->getMois().'/'.$releve->getAnnee() : '';
		$ancienIndex = $releve ? $releve->getAncienIndex() : '';
		$nouvelIndex = $releve ? $releve->getNouvelIndex() : '';
		$consommation = $releve ? $releve->getConsomation() : '';

		$data = [
			'nom' => $nom,
			'date' => $date,
			'mois' => $mois,
			'ancienIndex' => $ancienIndex,
			'nouvelIndex' => $nouvelIndex,
			'consommation' => $consommation
		];

		return new JsonResponse($data);
	}

    /**
     * @Route ("/deletes/releve",name="delere_releve")
     *
     */
    public function deleteReleve(Request $request)
    {
        $id = $request->request->get('id');

        if ($id != null){
           
           $releve = $this->releveRepository->find($id);
            $this->entityManager->remove($releve);
            $this->entityManager->flush();
            $delete = true;
            $this->addFlash('success', "succes du suppression");
        }

        return new JsonResponse(['form_delete' => $delete]);
    }

 /**
     * @Route ("/deletes/bf/releve",name="delete_bf_releve")
     *
     */
    public function deleteBfReleve(Request $request)
    {
        $id = $request->request->get('id');

        if ($id != null){
            $releve = $this->releveBfRepository->find($id);
            $this->entityManager->remove($releve);
            $this->entityManager->flush();
            $delete = true;
            $this->addFlash('success', "succes du suppression");
        }

        return new JsonResponse(['form_delete' => $delete]);
    }

    /**
     * @Route("/journals",name="journals_admin")
     */
    public function journals_admin(Request $request,PaginatorInterface $paginator,PaymentTrancheRepository $paymentTrancheRepository)
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
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
			$paymentTranches = $paymentTrancheRepository->findByYear($selectedYear);
                
        }elseif($selectedYear != null and $selectedMonth != null and $selectedDay == null and $selectJournal="mensuel"){   
            $releves = $this->releveRepository->findByYearMonthReleve($selectedYear,$selectedMonth);
			 $paymentTranches = $paymentTrancheRepository->findByYearMonth($selectedYear, $selectedMonth);
        }else{
            $releves = $this->releveRepository->findByYearMonthDayReleve($selectedYear, $selectedMonth, $selectedDay);
			$paymentTranches = $paymentTrancheRepository->findByYearMonthDay($selectedYear, $selectedMonth, $selectedDay);
        }
          $combinedData = array_merge($releves, $paymentTranches);
		  $pagination = $paginator->paginate(
			$combinedData, 
			$request->query->getInt('page', 1), 
			10 
		 );
        
        return $this->render('admin/journal.html.twig', [
            'releves' => $pagination,
            'years' => $years,
            'selectedYear' => $selectedYear,   
            'months'=>$months,
            'selectedMonth'=>$selectedMonth,
            'selectedDay'=>$selectedDay,
            'selectJournal'=>$selectJournal

        ]);
    }

    /**
     * @Route("/recouvrements",name="recouvrements_admin")
     */
    public function recouvrements_admin(Request $request, PaginatorInterface $paginator)
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_login');
        }
        $years = $this->releveRepository->findUniqueYears();
        $currentYear = date('Y');
        $selectedYear = $request->request->get('year', $currentYear);
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $query = $queryBuilder
            ->select('p')
            ->from(Releve::class, 'p')
            ->join('p.client', 'c')
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->isNull('p.factureDatePaiement'), // Assurez-vous que la date de paiement est nulle (impayé)
                    $queryBuilder->expr()->lte('p.dateReleve', ':current_date'),
                    $queryBuilder->expr()->isNull('c.coupure'),
                    $queryBuilder->expr()->eq(
                        sprintf('YEAR(p.dateReleve)'), // Utilisez YEAR() pour extraire l'année
                        ':annee'
                    )
                )
            )
            ->setParameter('current_date', new \DateTime())
            ->setParameter('annee', $selectedYear);

        $searchQuery = $request->request->get('search'); // Récupérer le paramètre de recherche depuis la requête

        if ($searchQuery) {
            $query->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like('c.nom', ':search'),
                    $queryBuilder->expr()->like('c.prenom', ':search'),
                    $queryBuilder->expr()->like('c.code', ':search')
                )
            )->setParameter('search', '%' . $searchQuery . '%');
        }

        $currentPage = $request->query->getInt('page', 1); // Récupérer le numéro de page actuel depuis la requête
        $perPage = 10; // Nombre d'éléments par page

        $impayes = $paginator->paginate(
            $query->getQuery(),
            $currentPage,
            $perPage
        );

        return $this->render('admin/impayes.html.twig', [
            'releves' => $impayes,
            'years' => $years,
            'selectedYear' => $selectedYear,
            'searchQuery' => $searchQuery,
        ]);

    }

    /**
     * @Route("/generate/excel", name="admin_generateExcel")
     */
    public function generateExcel()
    {
        $spreadsheet = new Spreadsheet();
        $quartiersData = $this->getQuartiersData(); // Supposons que vous récupériez les données des quartiers
        $dateFormatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMMM YYYY');
        foreach ($quartiersData as $quartier => $dataByMonth) {
            $spreadsheet->createSheet();
            $spreadsheet->setActiveSheetIndex($spreadsheet->getSheetCount() - 1);
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($quartier);
    
            // Obtenir la liste des mois pour ce quartier
            $months = array_keys($dataByMonth);
    
            // Entête de la feuille Excel
            $sheet->setCellValue('A1', 'Code');
            $sheet->setCellValue('B1', 'Client');
            $col = 'C';
    
            // Ajouter les en-têtes des mois
            foreach ($months as $month) {
                $explode = explode(' ', $month);
                $year = $explode[0];
                $mon = $explode[1];
                $monthName = $dateFormatter->format(new \DateTime("$year-$mon-01"));
                $sheet->setCellValue($col . '1', $monthName);
                $col++;
            }
    
            $row = 2; // Commencez à partir de la ligne 2
    
            // Collecter tous les clients uniques pour ce quartier
            $uniqueClients = [];
            foreach ($dataByMonth as $clientData) {
                foreach ($clientData as $clientName => $recouvrementData) {
                    $uniqueClients[$clientName] = true;
                }
            }
    
            // Parcourir les clients uniques
            foreach (array_keys($uniqueClients) as $clientName) {
                $explode = explode("code",$clientName);
                $sheet->setCellValue('A' . $row, $explode[1]);
                $sheet->setCellValue('B' . $row, $explode[0]);
    
                $col = 'C';
    
                // Parcourir les mois et obtenir les valeurs pour ce client
                foreach ($months as $month) {
                    $valuesForClient = [];
                   if(isset($dataByMonth[$month][$clientName])){
                    foreach ($dataByMonth[$month][$clientName] as $recouvrement) {
                        $consomation = $recouvrement->getConsomation();
                        $limite = $recouvrement->getLimite();
                        $pu = $recouvrement->getPu();
                        $pus = $recouvrement->getPus();
                        if($limite != null){
                            if ($consomation > $limite and $pus != null ) {
                                $cons = $consomation - $limite;
                                $valeur1 = $limite * $pu;
                                $valeur2 = $cons * $pus;
                                $totale = $valeur1 + $valeur2;
                                $percentage1 = ($totale * 5) / 100 ;
                                $percentage2 = ($totale * 1) / 100 ;
                                $percentage3 = ($totale * 2) / 100 ;
                                if( $consomation > 10){
                                    $consomation2 = ($consomation - 10) * $pus;
                                    $percentage4 = ($consomation2 * 20) / 100;
                                    $resultatfinale = $totale + 1000 + $percentage1 + $percentage2 + $percentage3 + $percentage3 + $percentage4;
                                }else {
                                    $resultatfinale = $totale + 1000 + $percentage1 + $percentage2 + $percentage3 + $percentage3;
                                }
                           }elseif($consomation <= $limite and $consomation != null){
                                $totale = $consomation * $pu;
                                $percentage1 = ($totale * 5) / 100 ;
                                $percentage2 = ($totale * 1) / 100 ;
                                $percentage3 = ($totale * 2) / 100 ;
                                if( $consomation > 10 and $pus != null){
                                    $consomation2 = ($consomation - 10) * $pus;
                                    $percentage4 = ($consomation2 * 20) / 100;
                                    $resultatfinale = $totale + 1000 + $percentage1 + $percentage2 + $percentage3 + $percentage3 + $percentage4;
                                }else {
                                    $resultatfinale = $totale + 1000 + $percentage1 + $percentage2 + $percentage3 + $percentage3;
                                }
                           }

                        }elseif($limite == null and $pu != null){

                            $totale = $consomation * $pu;
                            $percentage1 = ($totale * 5) / 100 ;
                            $percentage2 = ($totale * 1) / 100 ;
                            $percentage3 = ($totale * 2) / 100 ;
                            if( $consomation > 10 and $pus != null){
                                $consomation2 = ($consomation - 10) * $pus;
                                $percentage4 = ($consomation2 * 20) / 100;
                                $resultatfinale = $totale + 1000 + $percentage1 + $percentage2 + $percentage3 + $percentage3 + $percentage4;
                            }else {
                                $resultatfinale = $totale + 1000 + $percentage1 + $percentage2 + $percentage3 + $percentage3;
                            }

                        }
    
                        $valuesForClient[] = $resultatfinale;
                    }
                   }
                    
    
                    // Concaténez les valeurs pour ce client et insérez-les dans la cellule appropriée
                    $valuesString = implode(' ', $valuesForClient);
                    $sheet->setCellValue($col . $row, $valuesString);
    
                    $col++;
                }
    
                $row++;
            }
        }
        
        // Générez le fichier Excel
        $writer = new Xlsx($spreadsheet);
        $tempFilePath = tempnam(sys_get_temp_dir(), 'recouvrement_');
        $writer->save($tempFilePath);
        
        // Envoyez le fichier Excel au navigateur en tant que téléchargement
        $response = new Response(file_get_contents($tempFilePath));
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $date = new \DateTime('now', new \DateTimeZone('Indian/Antananarivo'));
        $timestamp = $date->format('Y-m-d_H-i-s');
        
        $filename = 'revouvrement_' . $timestamp . '.xlsx';
        
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
      
        $response->headers->set('Cache-Control', 'max-age=0');
        
        return $response;

        
    }
    private function getQuartiersData()
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        

        $query = $queryBuilder
            ->select('p')
            ->from(Releve::class, 'p')
            ->join('p.client', 'c')
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->isNull('p.factureDatePaiement'),
                    $queryBuilder->expr()->isNull('c.coupure')
                )
            )
            ->orderBy('p.dateReleve', 'ASC')
            ->getQuery();

        $impayes = $query->getResult();
        $recouvrementData = $impayes;

        $dataByQuartier = [];

        // Parcourez les données de recouvrement et groupez-les par quartier
        foreach ($recouvrementData as $recouvrement) {
           
            $quartier =$recouvrement->getClient()->getQuartier()->getNom();
            $month = $recouvrement->getDateReleve()->format('F Y');;
            $clientName = $recouvrement->getClient()->getNom() . ' ' . $recouvrement->getClient()->getPrenom() . 'code'. $recouvrement->getClient()->getCode();

            // Créez la structure de données hiérarchique
            if (!isset($dataByQuartier[$quartier])) {
                $dataByQuartier[$quartier] = [];
            }

            if (!isset($dataByQuartier[$quartier][$month])) {
                $dataByQuartier[$quartier][$month] = [];
            }

            if (!isset($dataByQuartier[$quartier][$month][$clientName])) {
                $dataByQuartier[$quartier][$month][$clientName] = [];
            }

            $dataByQuartier[$quartier][$month][$clientName][] = $recouvrement;
        }

        return $dataByQuartier;
        
    }


  

}
