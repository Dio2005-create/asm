<?php

namespace App\Controller\Audit;

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


/**
 * @Route("/audit")
 */
class AuditController extends AbstractController
{

    protected $clientRepository;
    protected $quartierRepository;
    protected $entityManager;
    protected $releveRepository;


    function __construct(
        ClientRepository $clientRepository,
        QuartierRepository $quartierRepository,
        EntityManagerInterface $entityManager,
        ReleveRepository $releveRepository

    ) {
        $this->clientRepository=$clientRepository;
        $this->quartierRepository=$quartierRepository;
        $this->entityManager=$entityManager;
        $this->releveRepository=$releveRepository;
    }

   /**
     * @Route("/", name="audit")
     */
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        if (!$this->isGranted('ROLE_AUDIT')) {
            return $this->redirectToRoute('app_login');
        }

        $query = $this->clientRepository->createQueryBuilder('c');
        $query->where('c.audit = true');

        $searchQuery = $request->query->get('search');
        if ($searchQuery) {
            $query->andWhere('c.nom LIKE :search OR c.prenom LIKE :search OR c.code LIKE :search')
                  ->setParameter('search', '%' . $searchQuery . '%');
        }

        $pagination = $paginator->paginate(
            $query->getQuery(),
            $request->query->getInt('page', 1), 
            10 
        );

        return $this->render('audit/client.html.twig', [
            'pagination' => $pagination,
            'search' => $searchQuery
        ]);
    }

     /**
 * @Route("/client/{id}", name="client_audit")
 */
public function getCLientAudit($id, PaginatorInterface $paginator, Request $request)
{
    if (!$this->isGranted('ROLE_AUDIT')) {
        return $this->redirectToRoute('app_login');
    }

     $client = $this->clientRepository->find($id);
    if (!$client || !$client->getAudit()) {
        throw $this->createNotFoundException('Client non trouvé ou audit non activé.');
    }

    $releveQuery = $this->releveRepository->createQueryBuilder('r')
		->join('r.client', 'c')
        ->where('r.client = :client')
        ->andWhere('c.audit = true')
        ->setParameter('client', $client)
        ->orderBy('r.id', 'DESC')
        ->getQuery();

    // Paginer les résultats
    $currentPage = $request->query->getInt('page', 1); // Récupérer le numéro de page actuel depuis la requête

    $releve = $paginator->paginate(
        $releveQuery,
        $currentPage,
        10 // Nombre d'éléments par page
    );

    return $this->render('audit/see_client.html.twig', [
        'client' => $client,
        'releves' => $releve
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
 * @Route("/export/audit/client", name="export_audit_client")
 */
public function export_audit_client()
{
    
   
    $clients = $this->clientRepository->findBy(['audit' => true]);
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
 * @Route("/generates/audit/excel/{year}", name="generates_audit_excel")
 */
public function generatesAuditExcel($year)
{
    
    $selectedYear = (int)$year;
    $releves = $this->releveRepository->findByYearReleveAudit($selectedYear);
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
 * @Route("/generer/audit/excel/{year}/mois/{month}", name="generer_audit_excel_mois")
 */
public function generer_audit_excel_mois($year,$month)
{
    
    $selectedYear = (int)$year;
    $releves = $this->releveRepository->findByYearMonthReleveAudit($selectedYear,$month);
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
 * @Route("/generates/excel/audit/year/{year}/month/{month}/day/{day}", name="generates_excel_audit_day")
 */
public function generatesAuditExcelDay($year,$month,$day)
{
    $releves = $this->releveRepository->findByYearMonthDayReleveAudit($year,$month,$day);
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
  * @Route("/relevea",name="releve_audit")
  */
 public function getReleveAudit(Request $request, PaginatorInterface $paginator)
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
        $releves = $this->releveRepository->findByYearQuartierAndMonthAudit($selectedYear, $selectedQuartier, $selectedMonth);
    } else {
        $releves = $this->releveRepository->findByMonthYearAudit($selectedYear,$selectedMonth);
    }
   
    $client = [];
    $form = $this->createForm(ReleveType::class,$client);
    return $this->render('audit/releve.html.twig',[
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
 * @Route("/export/releves", name="export_releves_to_excel")
 */
public function exportReleves(Request $request)
{
    $selectedYear = $request->request->get('year', date('Y'));
    $selectedMonth = $request->request->get('month', date('m'));
    $selectedQuartier = $request->request->get('quartier', null);
    $releves = $this->releveRepository->findByYearQuartierAndMonth($selectedYear, $selectedQuartier, $selectedMonth);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

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

    // Ajoutez les en-têtes à la feuille Excel
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    $row = 2; // Commencez à partir de la ligne 2 car la première ligne contient les en-têtes

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
     * @Route("/journal",name="journals_audit")
     */
    public function journals_audit(Request $request,PaginatorInterface $paginator,PaymentTrancheRepository $paymentTrancheRepository)
    {
        if (!$this->isGranted('ROLE_AUDIT')) {
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
            $releves = $this->releveRepository->findByYearReleveAudit($selectedYear);
			$paymentTranches = $paymentTrancheRepository->findByYearAudit($selectedYear);
                
        }elseif($selectedYear != null and $selectedMonth != null and $selectedDay == null and $selectJournal="mensuel"){   
            $releves = $this->releveRepository->findByYearMonthReleveAudit($selectedYear,$selectedMonth);
			 $paymentTranches = $paymentTrancheRepository->findByYearMonthAudit($selectedYear, $selectedMonth);
        }else{
            $releves = $this->releveRepository->findByYearMonthDayReleveAudit($selectedYear, $selectedMonth, $selectedDay);
			$paymentTranches = $paymentTrancheRepository->findByYearMonthDayAudit($selectedYear, $selectedMonth, $selectedDay);
        }
          $combinedData = array_merge($releves, $paymentTranches);
		  $pagination = $paginator->paginate(
			$combinedData, 
			$request->query->getInt('page', 1), 
			10 
		 );
        
        return $this->render('audit/journal.html.twig', [
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
     * @Route("/generate/excel", name="generateExcel")
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
	
	/**
 * @Route("/dashboards",name="dashboard_audit")
 */
public function getDashboardAudit(Request $request, PaginatorInterface $paginator){
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
      // Définir l'audit souhaité
        $audit = true; // ou récupérez la valeur souhaitée

        // Utilisation du gestionnaire d'entités pour créer et exécuter une requête DQL
        $query = $this->entityManager->createQuery(
            'SELECT COUNT(r.id)
            FROM App\Entity\Releve r
            JOIN r.client c
            WHERE r.annee = :annee
            AND r.mois = :mois
            AND c.audit = :audit'
        )->setParameters([
            'annee' => $annee,
            'mois' => $mois,
            'audit' => $audit
        ]);

        // Obtenir le nombre total de relevés
        $nombreTotalReleves = $query->getSingleScalarResult();
	
	$relevesTotale= $this->releveRepository
      ->createQueryBuilder('r')
	  ->join('r.client', 'c')
	  ->where('c.audit = true')
      ->andWhere('r.annee = :annee')
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
	  ->join('r.client', 'c')
      ->where('r.annee = :annee')
	  ->andWhere('c.audit = true')
      ->andWhere('r.mois = :mois')
      ->andWhere('r.factureDatePaiement IS NOT NULL')
      ->setParameter('annee', $annee)
      ->setParameter('mois', $mois)
      ->getQuery()
      ->getSingleScalarResult();
	
$relevesPayes= $this->releveRepository
      ->createQueryBuilder('r')
	  ->join('r.client', 'c')
	  ->where('c.audit = true')
      ->andWhere('r.annee = :annee')
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
	  ->join('r.client', 'c')
      ->select('COUNT(r.id)')
      ->where('r.annee = :annee')
      ->andWhere('r.mois = :mois')
	  ->andWhere('c.audit = true')
      ->andWhere('r.factureDatePaiement IS NULL')
      ->setParameter('annee', $annee)
      ->setParameter('mois', $mois)
      ->getQuery()
      ->getSingleScalarResult();

      
      // Récupérer les relevés non payés avec possibilité de filtre



// Exécuter la requête après avoir appliqué toutes les conditions
$relevesNonPayes = $query->getResult();
	
	$relevesNPayes= $this->releveRepository
      ->createQueryBuilder('r')
	  ->join('r.client', 'c')
      ->where('r.annee = :annee')
	  ->andWhere('c.audit = true')
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
      


  return $this->render('audit/index.html.twig', [
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
