Pendable Symfony Mailer
=================

Provides Pendable integration for Symfony Mailer.

Installation
------------

Open a command console in your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require pendable/symfony-mailer
```

Add the following line on `config/services.php`

```php
<?php
// config/services.php

return [
    Pendable\SymfonyMailer\Transport\PendableTransportFactory:
        tags: [ mailer.transport_factory ]
];
```

Finally, add your MAILER_DSN credentials into your `.env` file of your project:

You can use HTTP API transport by configuring your DSN as this:

```env
MAILER_DSN=pendable+api://$PENDABLE_API_KEY@default
```

## Usage

```php
$email = (new Email())
            ->from('mydomain@test.com')
            ->to('to@test.com')
            // ->cc('...')
            // ->addCc('...')
            // ->bcc('...')
            // ->replyTo('...')
            ->subject("Subject")
            ->html('<p>Html content of Symfony Pendable email.</p>')
            ->text('Text content of your Symfony Pendable text-only email.');

        $email->getHeaders()
            ->add(new MetadataHeader('key-1', 'value-1')) // Custom field called 'key-1'
            ->add(new MetadataHeader('key-2', 'value-2')) // Custom field called 'key-2'
            ->add(new TagHeader('TagInHeaders1')) // Tags
            ->add(new TagHeader('TagInHeaders2')) // Tags
            ->addTextHeader('priority', 60)
            ->addTextHeader('config_identifier', 'custom key')
            ->addTextHeader('client_email_id', '123476AB')
            ->addTextHeader('schedule_send_at', '2023-06-25T22:37:26+05:30');

        // Send the email using the custom Pendable transport
        try {
            $resp = $mailer->send($email);
            return new Response('Email sent successfully to Pendable.');
        } catch (\Exception $e) {
            return new Response('Failed to send email to Pendable: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
```

Resources
---------

* [Report issues](https://github.com/pendable/symfony-mailer)
* [Pendable Documentation](https://pendable.io/documentation)

