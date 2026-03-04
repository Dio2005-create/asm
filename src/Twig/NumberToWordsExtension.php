<?php
// src/Twig/NumberToWordsExtension.php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class NumberToWordsExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('numberToWords', [$this, 'convertToWords']),
        ];
    }

    public function convertToWords($number)
    {
        $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::SPELLOUT);

        // Formatez le nombre en lettres
        $words = $formatter->format($number);

        // Convertissez le résultat en majuscules
        $wordsInUppercase = mb_strtoupper($words, 'UTF-8');

        return $wordsInUppercase;     
    }
}
