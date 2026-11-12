<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Tilttek\AccessibleDice\TilttekAccessibleDiceBundle;
use Tilttek\DiceBundle\TilttekDiceBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TilttekDiceBundle::class => ['all' => true],
    TilttekAccessibleDiceBundle::class => ['all' => true],
];
