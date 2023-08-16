<?php

namespace Pendable\SymfonyMailer\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mailer\Header\TagHeader;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;


/**
 * @author Jaspal Singh <jaspal@pendable.io>
 */
class PendableApiTransport extends AbstractApiTransport
{
    private const ENDPOINT = 'https://api.pendable.io';

    private string $key;

    public function __construct(string $key, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('pendable+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $payload = $this->getPayload($email, $envelope);

        $response = $this->client->request('POST', $this->getEndpoint() . '/emails', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->key,
            ],
            'json' => $payload,
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: ' . $response->getContent(false) . sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Pendable server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {

            $errorMessage = "";
            // dd($result);
            if (isset($result['detail']) && is_array($result['detail'])) {
                foreach ($result['detail'] as $error) {
                    if (isset($error['loc'][1])) {
                        $errorMessage .= "\"" . $error['loc'][1] . "\" - " . $error['msg'] . ". " . PHP_EOL;
                    }
                }
            }

            throw new HttpTransportException($errorMessage, $response);
        }

        $sentMessage->setMessageId($result['pendable_id']);

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {

        $payload = [
            'from' => $envelope->getSender()->toString(),
            // @todo - support multiple to addresses
            'to' => current($this->stringifyAddresses($this->getRecipients($email, $envelope))),
            'subject' => $email->getSubject(),
        ];
        if ($email->getReplyTo()) {
            $payload['reply_to'] = current($this->stringifyAddresses($email->getReplyTo()));
        }
        if ($email->getCc()) {
            $payload['cc'] = $this->stringifyAddresses($email->getCc());
        }
        if ($email->getBcc()) {
            $payload['bcc'] = $this->stringifyAddresses($email->getBcc());
        }
        if ($email->getTextBody()) {
            $payload['text_body'] = $email->getTextBody();
        }
        if ($email->getHtmlBody()) {
            $payload['html_body'] = $email->getHtmlBody();
        }

        $headersAndTags = $this->prepareHeadersAndTags($email->getHeaders());
        if ($headersAndTags) {
            $payload = array_merge($payload, $headersAndTags);
        }

        return $payload;
    }

    private function prepareHeadersAndTags(Headers $headers): array
    {
        $headersAndTags = [];
        $headersToBypass = ['from', 'sender', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'content-type', 'accept', 'api-key'];
        foreach ($headers->all() as $name => $header) {

            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $headersAndTags['tags'][] = $header->getValue();
                continue;
            }

            if ($header instanceof MetadataHeader) {
                $headersAndTags['custom_fields'][$header->getKey()] = $header->getValue();
                continue;
            }

            switch ($name) {
                case 'priority':
                case 'config_identifier':
                case 'client_email_id':
                case 'schedule_send_at':
                    $headersAndTags[$header->getName()] = $header->getValue();
                    break;

                default:
                    $headersAndTags['headers'][$header->getName()] = $header->getBodyAsString();
                    break;
            }
        }

        return $headersAndTags;
    }

    private function getEndpoint(): ?string
    {
        $endpoint = self::ENDPOINT;

        if (!empty($_ENV['PENDABLE_API_ENDPOINT'])) {
            $endpoint = $_ENV['PENDABLE_API_ENDPOINT'];
        }

        return rtrim($endpoint, '/');
    }

}