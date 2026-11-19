<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Tilttek\DiceBundle\TilttekDiceBundle;
use Tilttek\DiceProbabilityBundle\TilttekDiceProbabilityBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    StimulusBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    LiveComponentBundle::class => ['all' => true],
    TilttekDiceBundle::class => ['all' => true],
    TilttekDiceProbabilityBundle::class => ['all' => true],
];
