<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Serialization;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageTyped;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class PhpSerializerTest extends TestCase
{
    public function testEncodedIsDecodable()
    {
        $serializer = new PhpSerializer();

        $envelope = new Envelope(new DummyMessage('Hello'));

        $encoded = $serializer->encode($envelope);
        $this->assertStringNotContainsString("\0", $encoded['body'], 'Does not contain the binary characters');
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    public function testDecodingFailsWithMissingBodyKey()
    {
        $serializer = new PhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Encoded envelope should have at least a "body", or maybe you should implement your own serializer');

        $serializer->decode([]);
    }

    public function testDecodingFailsWithBadFormat()
    {
        $serializer = new PhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        $serializer->decode([
            'body' => '{"message": "bar"}',
        ]);
    }

    public function testDecodingFailsWithBadBase64Body()
    {
        $serializer = new PhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        $serializer->decode([
            'body' => 'x',
        ]);
    }

    public function testDecodingFailsWithBadClass()
    {
        $serializer = new PhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/class "ReceivedSt0mp" not found/');

        $serializer->decode([
            'body' => 'O:13:"ReceivedSt0mp":0:{}',
        ]);
    }

    public function testEncodedSkipsNonEncodeableStamps()
    {
        $serializer = new PhpSerializer();

        $envelope = new Envelope(new DummyMessage('Hello'), [
            new DummyPhpSerializerNonSendableStamp(),
        ]);

        $encoded = $serializer->encode($envelope);
        $this->assertStringNotContainsString('DummyPhpSerializerNonSendableStamp', $encoded['body']);
    }

    public function testNonUtf8IsBase64Encoded()
    {
        $serializer = new PhpSerializer();

        $envelope = new Envelope(new DummyMessage("\xE9"));

        $encoded = $serializer->encode($envelope);
        $this->assertTrue((bool) preg_match('//u', $encoded['body']), 'Encodes non-UTF8 payloads');
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    /**
     * @requires PHP 7.4
     */
    public function testDecodingFailsForPropertyTypeMismatch()
    {
        $serializer = new PhpSerializer();
        $encodedEnvelope = $serializer->encode(new Envelope(new DummyMessageTyped('true')));
        // Simulate a change of property type in the code base
        $encodedEnvelope['body'] = str_replace('s:4:\"true\"', 'b:1', $encodedEnvelope['body']);

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        $serializer->decode($encodedEnvelope);
    }
}

class DummyPhpSerializerNonSendableStamp implements NonSendableStampInterface
{
}
