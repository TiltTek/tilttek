<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Tilttek\ActivityBundle\TilttekActivityBundle;
use Tilttek\LeaderboardsBundle\TilttekLeaderboardsBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    PhilPrivacyBundle::class => ['all' => true],
    TilttekActivityBundle::class => ['all' => true],
    TilttekLeaderboardsBundle::class => ['all' => true],
];
