<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations. The browser
 * suite, under tests/Browser, drives a Chrome and runs apart from the monorepo's suites.
 */
return [
    'paths' => ['src', 'tests'],
    // The accessibility guidelines the targets are measured against, and the driver of the browser suite.
    'docs' => ['public' => true, 'words' => ['WCAG', 'chromedriver']],
];
