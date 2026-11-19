<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\ContentWarningsBundle\PhilContentWarningsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Tilttek\TableSafetyBundle\TilttekTableSafetyBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilContentWarningsBundle::class => ['all' => true],
    TilttekTableSafetyBundle::class => ['all' => true],
];
