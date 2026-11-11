<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\OpenBadgesBundle\PhilOpenBadgesBundle;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Tilttek\ActivityBundle\TilttekActivityBundle;
use Tilttek\BadgesBundle\TilttekBadgesBundle;

// phil/privacy-bundle and phil/open-badges-bundle are optional integrations: the application runs without them.
return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    ...(class_exists(PhilPrivacyBundle::class) ? [PhilPrivacyBundle::class => ['all' => true]] : []),
    TilttekActivityBundle::class => ['all' => true],
    ...(class_exists(PhilOpenBadgesBundle::class) ? [PhilOpenBadgesBundle::class => ['all' => true]] : []),
    TilttekBadgesBundle::class => ['all' => true],
];
