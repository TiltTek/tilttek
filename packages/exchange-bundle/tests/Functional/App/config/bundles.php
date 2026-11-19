<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tilttek\ExchangeBundle\TilttekExchangeBundle;
use Tilttek\PointsBundle\TilttekPointsBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilPrivacyBundle::class => ['all' => true],
    TilttekPointsBundle::class => ['all' => true],
    TilttekExchangeBundle::class => ['all' => true],
];
