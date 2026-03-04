<?php

namespace App\Form;

use App\Entity\Quartier;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id',HiddenType::class)
            ->add('nom')
            ->add('prenom',null,[
                'required'=>false
            ])
            ->add('quartier',EntityType::class,
                array(
                    'required'=>true,
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('f');
                    },
                    'choice_value' => 'id',
                    'class' => Quartier::class,
                    'choice_label' => function (?Quartier $quartier) {
                        return $quartier ? strtoupper($quartier->getNom()) : '';
                    },
                    'attr' => array('class' => 'form-control chosen-select'),
                    'placeholder' => 'Quartier'

                )
            )
            ->add('adresse',TextareaType::class,[
                'required'   => false,
                'attr' => ['class' => 'form-control','style' => 'height:90px']
            ])
			
			 ->add('isSpecific', ChoiceType::class, [
                'label' => 'Client spécifique (-50000)',
                'choices' => [
                    'Oui' => 1,
                    'Non' => 0,
                ],
                'expanded' => true, 
                'multiple' => false
            ])
			
		   ->add('audit', ChoiceType::class, [
                'label' => 'Audit',
                'choices' => [
                    'Oui' => 1,
                    'Non' => 0,
                ],
                'expanded' => true, 
                'multiple' => false
            ])

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
