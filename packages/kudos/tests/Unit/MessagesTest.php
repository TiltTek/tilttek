<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Kudos\Exception\InvalidKudos;
use Tilttek\Kudos\Exception\UnknownMessage;
use Tilttek\Kudos\Message;
use Tilttek\Kudos\Messages;

#[CoversClass(Message::class)]
#[CoversClass(Messages::class)]
#[CoversClass(InvalidKudos::class)]
#[CoversClass(UnknownMessage::class)]
final class MessagesTest extends TestCase
{
    public function testTheDefaultListHoldsSixMessagesInOrder(): void
    {
        $messages = Messages::defaults();

        self::assertSame(['help', 'well_played', 'great_idea', 'welcome', 'good_game', 'great_session'], $this->names($messages));
        self::assertSame('Thanks for your help', $messages->get('help')->text);
        self::assertSame('kudos.message.help', $messages->get('help')->translationKey());
    }

    public function testAnApplicationAddsReplacesAndRemovesMessages(): void
    {
        $messages = Messages::defaults()
            ->with(new Message('help', 'Thank you so much'), new Message('nice_map', 'Nice map'), new Message('messages', 'Thanks for the messages'))
            ->without('welcome', 'good_game', 'not_listed');

        self::assertSame(['help', 'well_played', 'great_idea', 'great_session', 'nice_map', 'messages'], $this->names($messages));
        self::assertSame('Thank you so much', $messages->get('help')->text);
        self::assertTrue($messages->has('nice_map'));
        self::assertFalse($messages->has('welcome'));
        self::assertTrue(Messages::defaults()->has('welcome'));
    }

    public function testAMessageOutsideTheListIsUnknown(): void
    {
        $this->expectException(UnknownMessage::class);
        $this->expectExceptionMessageIsOrContains('"you rock" is not one of the messages thanks are given with.');

        Messages::defaults()->get('you rock');
    }

    public function testTwoMessagesCannotShareAName(): void
    {
        $this->expectException(InvalidKudos::class);
        $this->expectExceptionMessageIsOrContains('Two messages are named "help".');

        new Messages(new Message('help', 'Thanks'), new Message('help', 'Thank you'));
    }

    public function testAListHoldsAtMostOneHundredMessages(): void
    {
        $hundred = array_map(static fn (int $i): Message => new Message('m'.$i, 'Text'), range(1, 100));

        self::assertCount(100, new Messages(...$hundred)->all());

        $this->expectException(InvalidKudos::class);
        $this->expectExceptionMessageIsOrContains('A list holds at most 100 messages; this one holds 101.');

        new Messages(...[...$hundred, new Message('m101', 'Text')]);
    }

    public function testANameAndATextHaveBounds(): void
    {
        $longest = new Message('a'.str_repeat('b', 63), str_repeat('x', 200));

        self::assertSame(64, \strlen($longest->name));
        self::assertSame(200, \strlen($longest->text));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidMessages(): iterable
    {
        yield 'empty name' => ['', 'Text', '"" is not a message name: 1 to 64 lower-case letters, digits and "_", starting with a letter.'];
        yield 'leading digit' => ['1st', 'Text', '"1st" is not a message name'];
        yield 'upper case' => ['Help', 'Text', '"Help" is not a message name'];
        yield 'dash' => ['well-played', 'Text', '"well-played" is not a message name'];
        yield 'line break' => ["help\n", 'Text', 'is not a message name'];
        yield 'long name' => ['a'.str_repeat('b', 64), 'Text', 'is not a message name'];
        yield 'empty text' => ['help', '', 'The text of the message "help" is 1 to 200 bytes; it is 0.'];
        yield 'long text' => ['help', str_repeat('x', 201), 'The text of the message "help" is 1 to 200 bytes; it is 201.'];
    }

    #[DataProvider('invalidMessages')]
    public function testAMessageOutOfBoundsIsRefused(string $name, string $text, string $error): void
    {
        $this->expectException(InvalidKudos::class);
        $this->expectExceptionMessageIsOrContains($error);

        new Message($name, $text);
    }

    /**
     * @return list<string>
     */
    private function names(Messages $messages): array
    {
        return array_map(static fn (Message $message): string => $message->name, $messages->all());
    }
}
