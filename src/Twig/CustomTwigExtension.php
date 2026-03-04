<?php
// src/Twig/CustomTwigExtension.php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigTest;

class CustomTwigExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('explode', [$this, 'explodeFilter']),
        ];
    }
	 public function getTests()
    {
        return [
            new TwigTest('releve', [$this, 'isReleve']),
            new TwigTest('paiement', [$this, 'isPaiement']),
            new TwigTest('autreEntite', [$this, 'isAutreEntite']),
        ];
    }

    public function explodeFilter($string, $delimiter)
    {
        return explode($delimiter, $string);
    }
	
	 public function isReleve($value)
    {
        return $value instanceof \App\Entity\Releve;
    }

    public function isPaiement($value)
    {
        return $value instanceof \App\Entity\PaymentTranche;
    }

    public function isAutreEntite($value)
    {
        return $value instanceof \App\Entity\AutreEntite;
    }
}
