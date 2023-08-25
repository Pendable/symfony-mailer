<?php

namespace Transport;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Pendable\SymfonyMailer\Transport\PendableApiTransport;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Pendable\SymfonyMailer\Transport\PendableTransportFactory;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;


/**
 * Class PendableTransportFactoryTest
 * @author Christopher Espiritu <chris@pendable.io>
 */
class PendableTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new PendableTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('pendable', 'default'),
            true,
        ];

        yield [
            new Dsn('pendable+api', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        yield [
            new Dsn('pendable', 'default', self::USER, self::PASSWORD),
            new PendableApiTransport(self::USER, new MockHttpClient(), null, new NullLogger()),
        ];

        yield [
            new Dsn('pendable+api', 'default', self::USER),
            new PendableApiTransport(self::USER, new MockHttpClient(), null, new NullLogger()),
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('pendable+foo', 'default', self::USER, self::PASSWORD),
            'The "pendable+foo" scheme is not supported; supported schemes for mailer "pendable" are: "pendable", "pendable+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('pendable', 'default')];

        yield [new Dsn('pendable+api', 'default')];
    }
}