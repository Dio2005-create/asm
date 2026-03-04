<?php

namespace App\Form;


use App\Entity\ClientBf;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReleveBfType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id',HiddenType::class)
            ->add('client',EntityType::class,
                array(
                    'required'=>true,
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('f');
                    },
                    'choice_value' => 'id',
                    'class' => ClientBf::class,
                    'choice_label' => function (?ClientBf $client) {
                        $code = explode("/",$client->getCode());
                        if(isset($code[2])){
                            $codes = $code[2];
                        }else{
                            $codes= $code[1];
                        }
                        return $client ? strtoupper(ltrim($codes, '0')) : '';
                    },
                    'attr' => array('class' => ' chosen-select'),
                    'placeholder' => 'Client'

                )
            )
           

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
