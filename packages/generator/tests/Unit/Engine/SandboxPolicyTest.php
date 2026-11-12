<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Engine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tilttek\Generator\Definition\Entry;
use Tilttek\Generator\Engine\SandboxPolicy;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedPropertyError;
use Twig\Sandbox\SecurityNotAllowedTagError;

#[CoversClass(SandboxPolicy::class)]
final class SandboxPolicyTest extends TestCase
{
    public function testAnEntryShowsItsTextAndFieldsOnly(): void
    {
        $policy = new SandboxPolicy();
        $entry = new Entry('a');

        $policy->checkMethodAllowed($entry, '__toString');
        $policy->checkMethodAllowed($entry, '__TOSTRING');
        $policy->checkPropertyAllowed($entry, 'price');
        $policy->checkSecurity(['set'], ['upper'], ['pick'], ['defined']);
        $policy->checkSecurity(['set'], ['upper'], ['pick']);

        $this->expectException(SecurityNotAllowedMethodError::class);
        $this->expectExceptionMessageIsOrContains('Calling "toArray" on a "Tilttek\Generator\Definition\Entry" object is not allowed.');
        $policy->checkMethodAllowed($entry, 'toArray');
    }

    public function testNoOtherObjectHasMethods(): void
    {
        $this->expectException(SecurityNotAllowedMethodError::class);
        $this->expectExceptionMessageIsOrContains('Calling "__toString" on a "stdClass" object is not allowed.');

        new SandboxPolicy()->checkMethodAllowed(new stdClass(), '__toString');
    }

    public function testNoOtherObjectHasProperties(): void
    {
        $this->expectException(SecurityNotAllowedPropertyError::class);
        $this->expectExceptionMessageIsOrContains('Reading "name" of a "stdClass" object is not allowed.');

        new SandboxPolicy()->checkPropertyAllowed(new stdClass(), 'name');
    }

    public function testExtendsIsNotAllowed(): void
    {
        $this->expectException(SecurityNotAllowedTagError::class);

        new SandboxPolicy()->checkSecurity(['extends'], [], [], []);
    }

    public function testAFilterOutsideTheListIsNotAllowed(): void
    {
        $this->expectException(SecurityNotAllowedFilterError::class);

        new SandboxPolicy()->checkSecurity([], ['raw'], [], []);
    }
}
