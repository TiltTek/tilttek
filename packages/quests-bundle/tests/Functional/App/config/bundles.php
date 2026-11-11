<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Tilttek\ActivityBundle\TilttekActivityBundle;
use Tilttek\QuestsBundle\TilttekQuestsBundle;

// phil/privacy-bundle is an optional integration: the application runs without it.
return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    ...(class_exists(PhilPrivacyBundle::class) ? [PhilPrivacyBundle::class => ['all' => true]] : []),
    TilttekActivityBundle::class => ['all' => true],
    TilttekQuestsBundle::class => ['all' => true],
];
