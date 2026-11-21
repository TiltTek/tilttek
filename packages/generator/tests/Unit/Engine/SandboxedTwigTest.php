<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Engine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Engine\SandboxedTwig;
use Tilttek\Generator\Engine\SandboxPolicy;
use Tilttek\Generator\Tests\Unit\Fixture\SpyLoader;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedTagError;

#[CoversClass(SandboxedTwig::class)]
#[CoversClass(SandboxPolicy::class)]
final class SandboxedTwigTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function templatesThatNameAnother(): iterable
    {
        yield 'use' => ["{% use 'target' %}a"];
        yield 'include' => ["{% include 'target' %}"];
        yield 'import' => ["{% import 'target' as t %}a"];
        yield 'from' => ["{% from 'target' import m %}a"];
        yield 'embed' => ["{% embed 'target' %}{% endembed %}"];
        yield 'extends' => ["{% extends 'target' %}"];
    }

    #[DataProvider('templatesThatNameAnother')]
    public function testATagThatNamesAnotherTemplateIsRefusedBeforeItLoads(string $source): void
    {
        $loader = new SpyLoader(['main' => $source, 'target' => '{% block b %}{% endblock %}{% macro m() %}{% endmacro %}']);
        $twig = new SandboxedTwig($loader);

        try {
            $twig->sandbox->render('main');
            self::fail('The template should have been refused.');
        } catch (SecurityNotAllowedTagError) {
        }

        self::assertNotContains('target', $loader->asked);
        self::assertContains('main', $loader->asked);
    }

    public function testAnAllowedTemplateRendersWithoutEscaping(): void
    {
        $twig = new SandboxedTwig(new ArrayLoader(['main' => "{% set a = '<b>' %}{% for i in [1, 2] %}{{ a|upper }}{% endfor %}{% if x is defined %}!{% endif %}"]));

        self::assertSame('<B><B>', $twig->sandbox->render('main'));
    }

    public function testAFilterOutsideThePolicyIsRefused(): void
    {
        $twig = new SandboxedTwig(new ArrayLoader(['main' => "{{ 'a'|raw }}"]));

        $this->expectException(SecurityNotAllowedFilterError::class);

        $twig->sandbox->render('main');
    }

    public function testAnUndefinedVariableIsAnError(): void
    {
        $twig = new SandboxedTwig(new ArrayLoader(['main' => '{{ nope }}']));

        $this->expectExceptionMessageIsOrContains('Variable "nope" does not exist');

        $twig->sandbox->render('main');
    }

    public function testTheEnvironmentKeepsItsCache(): void
    {
        $directory = sys_get_temp_dir().'/tilttek-generator-sandboxed-'.bin2hex(random_bytes(4));

        self::assertSame($directory, new SandboxedTwig(new ArrayLoader(), $directory)->environment->getCache());
        self::assertFalse(new SandboxedTwig(new ArrayLoader())->environment->getCache());
    }
}
