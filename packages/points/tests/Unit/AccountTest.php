<?php

declare(strict_types=1);

namespace Tilttek\Points\Tests\Unit;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Points\Account;
use Tilttek\Points\AccountType;
use Tilttek\Points\Exception\InvalidPoints;

#[CoversClass(Account::class)]
#[CoversClass(AccountType::class)]
#[CoversClass(InvalidPoints::class)]
final class AccountTest extends TestCase
{
    public function testTheAccounts(): void
    {
        $member = Account::member(ResourceReference::of('member', '44'));

        self::assertSame([AccountType::Member, 'member:44', 'member:member:44'], [$member->type, $member->name, $member->toString()]);
        self::assertTrue($member->isMember());
        self::assertSame('member:44', $member->memberReference()?->toString());
        self::assertSame('issuance:games', Account::issuance('games')->toString());
        self::assertSame('redemption:reward:12', Account::redemption('reward:12')->toString());
        self::assertSame('expiry:expired', Account::expiry()->toString());
        self::assertSame('erased:member', Account::erased()->toString());
        self::assertSame(AccountType::Issuance, Account::issuance('games')->type);
        self::assertSame(AccountType::Redemption, Account::redemption('r')->type);
        self::assertSame(AccountType::Expiry, Account::expiry()->type);
        self::assertSame(AccountType::Erased, Account::erased()->type);

        foreach ([Account::issuance('games'), Account::redemption('r'), Account::expiry(), Account::erased()] as $other) {
            self::assertFalse($other->isMember());
            self::assertNull($other->memberReference());
        }
    }

    public function testNamesAreChecked(): void
    {
        self::assertSame('0', Account::issuance('0')->name);
        self::assertSame('a-b_c.d:9', Account::issuance('a-b_c.d:9')->name);
        self::assertSame(255, \strlen(Account::issuance(str_repeat('a', 255))->name));

        foreach (['', 'Games', '-games', 'a b', str_repeat('a', 256), "games\n"] as $name) {
            try {
                Account::redemption($name);
                self::fail(\sprintf('"%s" was expected to be refused.', $name));
            } catch (InvalidPoints $invalidPoints) {
                self::assertSame(\sprintf('"%s" is not an account name: 1 to 255 lower-case letters, digits, "_", ".", ":" or "-", starting with a letter or a digit.', $name), $invalidPoints->getMessage());
            }
        }
    }

    /**
     * @return iterable<array{string}>
     */
    public static function accounts(): iterable
    {
        yield ['member:member:44'];
        yield ['member:user:a:b'];
        yield ['issuance:games'];
        yield ['redemption:reward:12'];
        yield ['expiry:expired'];
        yield ['erased:member'];
    }

    #[DataProvider('accounts')]
    public function testAnAccountIsReadBackFromItsString(string $account): void
    {
        self::assertSame($account, Account::fromString($account)->toString());
        self::assertTrue(Account::fromString($account)->equals(Account::fromString($account)));
    }

    public function testFixedAccountsIgnoreTheNameRead(): void
    {
        self::assertSame('expiry:expired', Account::fromString('expiry:anything')->toString());
        self::assertSame('erased:member', Account::fromString('erased')->toString());
    }

    public function testEquality(): void
    {
        self::assertFalse(Account::issuance('a')->equals(Account::redemption('a')));
        self::assertFalse(Account::issuance('a')->equals(Account::issuance('b')));
    }

    public function testAStringThatIsNoAccount(): void
    {
        foreach (['bank:x', 'games', ''] as $account) {
            try {
                Account::fromString($account);
                self::fail(\sprintf('"%s" was expected to be refused.', $account));
            } catch (InvalidPoints $invalidPoints) {
                self::assertSame(\sprintf('"%s" is not an account: a type among member, issuance, redemption, expiry and erased, a colon and a name.', $account), $invalidPoints->getMessage());
            }
        }

        $this->expectException(InvalidPoints::class);
        Account::fromString('issuance:');
    }

    public function testAMemberAccountNeedsAReference(): void
    {
        $this->expectException(InvalidResource::class);
        Account::fromString('member:44');
    }
}
