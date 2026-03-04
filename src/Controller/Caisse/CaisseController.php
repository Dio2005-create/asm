<?php

namespace App\Controller\Caisse;

use App\Entity\Facture;
use App\Entity\PaymentTranche;
use App\Entity\Releve;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\ContractRepository;
use App\Repository\FactureRepository;
use App\Repository\PaymentTrancheRepository;
use App\Repository\QuartierRepository;
use App\Repository\ReleveRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use IntlDateFormatter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * @Route("/caisse")
 */
class CaisseController extends AbstractController
{

    private $clientRepository;
    private $quartierRepository;
    private $entityManager;
    private $releveRepository;
    private $userRepository; 
    private $contractRepository;

    function __construct(
        ClientRepository $clientRepository,
        QuartierRepository $quartierRepository,
        EntityManagerInterface $entityManager,
        ReleveRepository $releveRepository,
        UserRepository $userRepository,
        ContractRepository $contractRepository
    ) {
        $this->contractRepository=$contractRepository;
        $this->clientRepository=$clientRepository;
        $this->quartierRepository=$quartierRepository;
        $this->entityManager=$entityManager;
        $this->releveRepository=$releveRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/clients", name="caisse")
     */
    public function index(Request $request, PaginatorInterface $paginator)
    {
        if (!$this->isGranted('ROLE_CAISSE')) {
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

        return $this->render('caisse/client.html.twig', [
            'pagination' => $pagination,
            'search'=>$searchQuery
        ]);
}
    /**
     * @Route("/historique/payment",name="historique_payment")
     */
    public function historique_payment()
    {
        $today = new \DateTime('today');

        $tomorrow = new \DateTime('tomorrow');
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $user = $this->getUser();
        $query = $queryBuilder
            ->select('r')
            ->from(Releve::class, 'r')
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->gte('r.factureDatePaiement', ':start_date'),
                    $queryBuilder->expr()->lt('r.factureDatePaiement', ':end_date'),
                    $queryBuilder->expr()->eq('r.comptable', ':user')
                  
                )
            )
            ->setParameter('start_date', $today)
            ->setParameter('end_date', $tomorrow)
            ->setParameter('user', $user)
            ->getQuery();
        $releves = $query->getResult();
        // Initialisez un tableau pour stocker le nombre de relevés payés par mois
        $nombreRelevesParMois = [];

        // Parcourez les relevés et comptez-les par mois
        foreach ($releves as $releve) {
        $dateFormatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMMM YYYY');
        $mon = $releve->getDateReleve()->format('F');
        $year = $releve->getDateReleve()->format('Y');
        $mois = $dateFormatter->format(new \DateTime("$year-$mon-01"));
            
            if (!isset($nombreRelevesParMois[$mois])) {
                $nombreRelevesParMois[$mois] = 0;
            }
            $nombreRelevesParMois[$mois]++;
        }

        return $this->render('caisse/historique.html.twig', [
            'nombreRelevesParMois' => $nombreRelevesParMois,
        ]);
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
     * @Route("/client/{id}",name="client")
     */
    public function client($id, PaginatorInterface $paginator, Request $request){
        if (!$this->isGranted('ROLE_CAISSE')) {
            return $this->redirectToRoute('app_login');
        }
        $client = $this->clientRepository->find($id);
        $releveQuery = $this->releveRepository->createQueryBuilder('r')
                    ->where('r.client = :client')
                    ->setParameter('client', $client)
                    ->orderBy('r.id', 'DESC')
                    ->getQuery();

        $currentPage = $request->query->getInt('page', 1); // Récupérer le numéro de page actuel depuis la requête

        $releves = $paginator->paginate(
            $releveQuery,
            $currentPage,
            10 // Nombre d'éléments par page
        );
        return $this->render('caisse/see_client.html.twig',[
            'client'=>$client,
            'releves'=>$releves
        ]);

    }
   

    /**
     * @Route ("/payer/releve",name="payer_releve")
     *
     */
    public function payer_releve(Request $request)
    {
        $user = $this->getUser();
        $id = $request->request->get('id');
        $argent = $request->request->get('argent');
        $type = $request->request->get('type');
        $releves = $request->request->get('releves');
		$date = $request->request->get('date');

        if($releves != null){
            
            foreach ($releves as $releve) {
                
         $releve = $this->releveRepository->find(intval($releve));
          $releve->setType($type);
           $releve->setFactureDatePaiement(new \DateTime($date));
           $releve->setPayer(true);
           $releve->setComptable($user);
           $this->entityManager->persist($releve);
           $this->entityManager->flush();
           $facture = new Facture();
           $facture->setDate(new \DateTime($date));
           $facture->setCaisse($user);
           $facture->setReleve($releve);
           $facture->setClient($releve->getClient());
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
           $facture->setMontant($totaleFinale);
           $this->entityManager->persist($facture);
           $this->entityManager->flush();
            }
            $message = 'Le payement est effectué';
            $this->addFlash('success', $message);
        }
        
        if ($id != null){
           
           $releve = $this->releveRepository->find($id);
           $releve->setType($type);
           $releve->setFactureDatePaiement(new \DateTime($date));
           $releve->setPayer(true);
           $releve->setComptable($user);
           $this->entityManager->persist($releve);
           $this->entityManager->flush();
           $facture = new Facture();
           $facture->setDate(new \DateTime($date));
           $facture->setCaisse($user);
           $facture->setReleve($releve);
           $facture->setPayer($argent);
           $facture->setClient($releve->getClient());
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
           $facture->setMontant($totaleFinale);
           $reste = $argent - $totaleFinale;
           $facture->setReste($reste);
           $this->entityManager->persist($facture);
           $this->entityManager->flush();
           $message = 'Le payement est effectué';
           $this->addFlash('success', $message);
        
        }
        if($releves != null){
            $jsonString = json_encode($request->request->get('releves'));
            return $this->redirectToRoute('generates_factures_pdf', ['id'=>$jsonString,'argent'=>$argent], 302, ['target' => '_blank']);
 
        }else{
            return $this->redirectToRoute('generer_facture_pdf', ['id'=>$releve->getId()], 302, ['target' => '_blank']);
 
        }
         
    }
    /**
     * @Route("/generates_factures_pdf/{id}/argent/{argent}",name="generates_factures_pdf")
     */
    public function generatesFacturesPdf(Dompdf $dompdf,$id,$argent, FactureRepository $factureRepository)
    {
        // Configurez les options Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf->setOptions($options);
        $id = json_decode($id);
        $factures = [];
        $totalMontantFactures = 0;
        foreach($id as $releves){
            $releve = $this->releveRepository->find($releves);
            $facture = $factureRepository->findOneBy(['releve'=>$releve]);

            $factures[] = $facture;
            $totalMontantFactures += $facture->getMontant();
        }
        $reste = $argent - $totalMontantFactures;
        
        $html = $this->renderView('caisse/factures.html.twig', [
            'facture' => $factures,
            'argent'=>$argent,
            'reste'=>$reste,
            'montant'=>$totalMontantFactures,
            'image_path' => $this->getParameter('kernel.project_dir') . '/assets/images/image001.png',
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper([0,0,228,700]);

        // Générez le PDF
        $dompdf->render();

        // Renvoyez le PDF au navigateur en tant que réponse
        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }
    /**
     * @Route("/generer_facture_pdf/{id}",name="generer_facture_pdf")
     */
    public function genererFacturePdf(Dompdf $dompdf,$id,FactureRepository $factureRepository)
    {
        // Configurez les options Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf->setOptions($options);
        $releve = $this->releveRepository->find($id);
        $facture = $factureRepository->findOneBy(
            ['releve' => $releve],
            ['id' => 'DESC'] 
        );
        $html = $this->renderView('caisse/facture.html.twig', [
            'facture' => $facture,
            'image_path' => $this->getParameter('kernel.project_dir') . '/assets/images/image001.png',
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper([0,0,228,400]);

        // Générez le PDF
        $dompdf->render();

        // Renvoyez le PDF au navigateur en tant que réponse
        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }


    /**
     * @Route("/journal",name="journal_caisse")
     */
    public function journal_caisse()
    {
        if (!$this->isGranted('ROLE_CAISSE')) {
            return $this->redirectToRoute('app_login');
        }
        $user = $this->getUser();
        $today = new \DateTime('today');

        $tomorrow = new \DateTime('tomorrow');
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $query = $queryBuilder
            ->select('r')
            ->from(Releve::class, 'r')
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->gte('r.factureDatePaiement', ':start_date'),
                    $queryBuilder->expr()->lt('r.factureDatePaiement', ':end_date'),
                    $queryBuilder->expr()->eq('r.payer', ':payer'),
                    $queryBuilder->expr()->eq('r.comptable', ':user')
                )
            )
            ->setParameter('start_date', $today)
            ->setParameter('end_date', $tomorrow)
            ->setParameter('payer', true)
            ->setParameter('user', $user)
            ->getQuery();
        $releves = $query->getResult();
		
		 $queryBuilder2 = $this->entityManager->createQueryBuilder();
    $paymentTrancheQuery = $queryBuilder2
			->select('pt')
			->from(PaymentTranche::class, 'pt')
			->where(
				$queryBuilder->expr()->andX(
					$queryBuilder->expr()->gte('pt.date', ':start_date'),
					$queryBuilder->expr()->lt('pt.date', ':end_date'),
					$queryBuilder->expr()->eq('pt.caisse', ':user')
				)
			)
			->setParameter('start_date', $today)
			->setParameter('end_date', $tomorrow)
			->setParameter('user', $user)
			->getQuery();

		$paymentTranches = $paymentTrancheQuery->getResult();
         return $this->render('caisse/journal.html.twig', [
            'releves' => $releves,
			 'paymentTranches' => $paymentTranches,
        ]);
    }

    /**
     * @Route("/tranches",name="tranches")
     */
    public function tranches(){

        $contract = $this->contractRepository->findAll();
        return $this->render('caisse/contract.html.twig',[
            'contrat'=>$contract
        ]);
    }

    /**
     * @Route("/details/tranche/contrat/{id}",name="caisse_tranche")
     */
    public function caisse_tranche($id)
    {
        $contrat = $this->contractRepository->find($id);
        $releve = $this->releveRepository->findBy(['contract'=>$contrat]);
        return $this->render('caisse/see_contract.html.twig',[
            'contrat'=>$contrat,
            'releve'=>$releve
        ]);
    }
    /**
     * @Route("/payer_tranche",name="payer_tranche")
     */
    public function payer_tranche(Request $request)
    {
        $argent = $request->request->get('argent');
        $id = $request->request->get('id');
		$date = $request->request->get('date');

        $contrat = $this->contractRepository->find($id);
        $totale = $contrat->getTotalAMount();
        $restes = $contrat->getReste();
        if($restes == null){
            $reste = $totale - $argent;

        }else{
           $reste = $restes - $argent;
        }
        $contrat->setReste($reste);
        $this->entityManager->persist($contrat);
        $this->entityManager->flush();
        $payment = new PaymentTranche();
        $payment->setArgent($argent);
        $payment->setContrat($contrat);
        $payment->setDate(new \DateTime($date));
        $payment->setCaisse($this->getUser());
        $this->entityManager->persist($payment);
        $this->entityManager->flush();
        if($contrat->getReste() == 0 or $contrat->getReste() < 0){
            $releve = $this->releveRepository->findBy(['contract'=>$contrat]);
            foreach ($releve as $releve) {
                $releve->setFactureDatePaiement(new \DateTime('now', new \DateTimeZone('Indian/Antananarivo')));
                $releve->setPayer(true);
                $releve->setComptable($this->getUser());
                $this->entityManager->persist($releve);
            }
            $this->entityManager->flush();
        }
        return $this->redirectToRoute('generates_factures_contrat_pdf',['id'=>$contrat->getId(),'payment'=>$payment->getId()]);
    }

    /**
     * @Route("/generates_factures_contrat/{id}/payment/{payment}",name="generates_factures_contrat_pdf")
     */
    public function generatesFacturesContratPdf(Dompdf $dompdf,$id,$payment,PaymentTrancheRepository $paymentTrancheRepository)
    {
        // Configurez les options Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf->setOptions($options);
        $contrat = $this->contractRepository->find($id);
        $paye = $paymentTrancheRepository->find($payment);
        $releve = $this->releveRepository->findBy(['contract'=>$contrat]);
        $html = $this->renderView('caisse/factureTranche.html.twig', [
             'contrat'=>$contrat,
             'paye'=>$paye,
             'releve'=>$releve,
            'image_path' => $this->getParameter('kernel.project_dir') . '/assets/images/image001.png',
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper([0,0,228,700]);

        // Générez le PDF
        $dompdf->render();

        // Renvoyez le PDF au navigateur en tant que réponse
        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }

   
}
