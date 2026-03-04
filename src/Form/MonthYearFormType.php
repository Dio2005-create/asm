<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Repository\ReleveBfRepository;

class MonthYearFormType extends AbstractType
{
    private $releveBfRepository;

    public function __construct(ReleveBfRepository $releveBfRepository)
    {
        $this->releveBfRepository = $releveBfRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('year', ChoiceType::class, [
                'choices' => $this->getYearChoices(),
            ])
            ->add('month', ChoiceType::class, [
                'choices' => $this->getMonthChoices(),
            ]);
    }

    private function getYearChoices()
    {
        $uniqueYearResults = $this->releveBfRepository->findUniqueYears();

        // Extrayez les années à partir des résultats
        $years = array_map(function ($result) {
            return $result['year'];
        }, $uniqueYearResults);
    
        // Maintenant, $years est un tableau de valeurs uniques
        return $years;
    
    }

    private function getMonthChoices()
    {
        return [
            'Janvier' => '1',
            'Février' => '2',
            'Mars' => '3',
            'Avril' => '4',
            'Mai' => '5',
            'Juin' => '6',
            'Juillet' => '7',
            'Août' => '8',
            'Septembre' => '9',
            'Octobre' => '10',
            'Novembre' => '11',
            'Décembre' => '12',
        ];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // Add any default options for your form here
        ]);
    }
}
