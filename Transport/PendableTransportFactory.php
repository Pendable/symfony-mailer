<?php

namespace Pendable\SymfonyMailer\Transport;

use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;

/**
 * Class PendableTransportFactory
 * @author Jaspal Singh <jaspal@pendable.io>
 */
class PendableTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);

        if (!in_array($scheme, $this->getSupportedSchemes())) {
            throw new UnsupportedSchemeException($dsn, 'pendable', $this->getSupportedSchemes());
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new PendableApiTransport($user, $this->client, $this->dispatcher, $this->logger))
            ->setHost($host)
            ->setPort($port);

    }

    protected function getSupportedSchemes(): array
    {
        return ['pendable', 'pendable+api'];
    }
}