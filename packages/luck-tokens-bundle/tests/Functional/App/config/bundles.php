<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tilttek\LuckTokensBundle\TilttekLuckTokensBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilPrivacyBundle::class => ['all' => true],
    TilttekLuckTokensBundle::class => ['all' => true],
];
