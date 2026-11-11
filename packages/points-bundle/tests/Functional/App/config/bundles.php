<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tilttek\ActivityBundle\TilttekActivityBundle;
use Tilttek\PointsBundle\TilttekPointsBundle;

// phil/privacy-bundle is an optional integration: the application runs without it.
return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    ...(class_exists(PhilPrivacyBundle::class) ? [PhilPrivacyBundle::class => ['all' => true]] : []),
    TilttekActivityBundle::class => ['all' => true],
    TilttekPointsBundle::class => ['all' => true],
];
