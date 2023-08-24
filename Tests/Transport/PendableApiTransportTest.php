<?php

namespace Pendable\SymfonyMailer\Tests\Transport;

use ReflectionMethod;
use ReflectionException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpClient\Response\MockResponse;
use Pendable\SymfonyMailer\Transport\PendableApiTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;


class PendableApiTransportTest extends TestCase
{
    /**
     * @return void
     * @throws ReflectionException
     */
    public function testCustomHeader()
    {
        $email = new Email();

        $email->getHeaders()
            ->add(new MetadataHeader('key-1', 'value-1')) // Custom field called 'key-1'
            ->add(new MetadataHeader('key-2', 'value-2')) // Custom field called 'key-2'
            ->add(new TagHeader('TagInHeaders1')) // Tags
            ->add(new TagHeader('TagInHeaders2')) // Tags
            ->addTextHeader('priority', 60)
            ->addTextHeader('config_identifier', 'custom key')
            ->addTextHeader('client_email_id', '123476AB')
            ->addTextHeader('schedule_send_at', '2023-06-25T22:37:26+05:30');

        $envelope = new Envelope(new Address('test@pendable.io', 'Alice'), [new Address('bob@system.com', 'Bob')]);

        $transport = new PendableApiTransport('ACCESS_KEY');
        $method = new ReflectionMethod(PendableApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('tags', $payload);
        $this->assertTrue(in_array('TagInHeaders1', $payload['tags']));
        $this->assertTrue(in_array('TagInHeaders2', $payload['tags']));

        $this->assertArrayHasKey('priority', $payload);
        $this->assertEquals(60, $payload['priority']);
        $this->assertArrayHasKey('config_identifier', $payload);
        $this->assertEquals('custom key', $payload['config_identifier']);
        $this->assertArrayHasKey('client_email_id', $payload);
        $this->assertEquals('123476AB', $payload['client_email_id']);
        $this->assertArrayHasKey('schedule_send_at', $payload);
        $this->assertEquals('2023-06-25T22:37:26+05:30', $payload['schedule_send_at']);

        $this->assertArrayHasKey('custom_fields', $payload);
        $this->assertIsArray($payload['custom_fields']);
        $this->assertEquals('value-1', $payload['custom_fields']['key-1']);
        $this->assertEquals('value-2', $payload['custom_fields']['key-2']);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.pendable.io/emails', $url);
            $this->assertStringContainsString('Accept: application/json', $options['headers'][0] ?? '');

            return new MockResponse(json_encode(['detail' => 'Not Authenticated']), [
                'http_code' => 418,
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
            ]);
        });

        $transport = new PendableApiTransport('ACCESS_KEY', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('dev@pendable.io', 'Pendable Developer'))
            ->from(new Address('testing@pendable.io', 'Pendable'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Not Authenticated');
        $transport->send($mail);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.pendable.io/emails', $url);
            $this->assertStringContainsString('Accept: application/json', $options['headers'][0] ?? '');

            return new MockResponse(json_encode([
                'pendable_id' => '1234-1234-1234',
                "pendable_received_on" => "2023-08-24T11:59:46+00:00",
            ]), [
                'http_code' => 200,
            ]);
        });

        $transport = new PendableApiTransport('ACCESS_KEY', $client);

        $dataPart = new DataPart('body');
        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('dev@pendable.io', 'Pendable Developer'))
            ->from(new Address('from@pendable.io', 'Pendable'))
            ->text('Hello here!')
            ->html('Hello there!')
            ->addCc('foo@bar.fr')
            ->addBcc('foo@bar.fr')
            ->addReplyTo('foo@bar.fr')
            ->addPart($dataPart);

        $message = $transport->send($mail);

        $this->assertSame('1234-1234-1234', $message->getMessageId());
    }
}