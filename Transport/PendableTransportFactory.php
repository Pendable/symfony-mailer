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
        $transport = null;
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);

        if ('pendable+api' === $scheme || 'pendable' === $scheme) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            return (new PendableApiTransport($user, $this->client, $this->dispatcher, $this->logger))
                ->setHost($host)
                ->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'pendable', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['pendable+api'];
    }
}