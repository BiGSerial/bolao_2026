<?php

namespace App\Mail\Transport;

use App\Models\TransactionalMailLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\RawMessage;

class KingHostApiTransport extends AbstractTransport
{
    public function __toString(): string
    {
        return 'kinghost_api';
    }

    protected function doSend(SentMessage $message): void
    {
        $rawMessage = $message->getOriginalMessage();
        if (! $rawMessage instanceof RawMessage) {
            throw new TransportException('KingHost transport only supports raw messages.');
        }

        $email = $this->toEmail($rawMessage);
        $to = $this->firstAddress($email->getTo(), $message->getEnvelope());
        if ($to === null) {
            throw new TransportException('Email sem destinatário válido.');
        }

        $config = (array) config('services.kinghost_smtp', []);
        $token = trim((string) ($config['token'] ?? ''));
        if ($token === '') {
            throw new TransportException('KINGHOST_SMTP_API_TOKEN não configurado.');
        }

        $fromAddress = $this->firstAddress($email->getFrom(), null)
            ?? trim((string) ($config['from_address'] ?? ''));
        $subject = (string) $email->getSubject();
        $body = (string) ($email->getHtmlBody() ?: $email->getTextBody() ?: '');

        if ($fromAddress === '') {
            throw new TransportException('Remetente não configurado para KingHost.');
        }

        $this->guardMonthlyLimit((int) ($config['monthly_limit'] ?? 0));

        $payload = [
            'from' => $fromAddress,
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ];

        $log = TransactionalMailLog::create([
            'provider' => 'kinghost_smtp',
            'from_address' => $fromAddress,
            'to_address' => $to,
            'subject' => $subject,
            'status' => 'sending',
            'attempts' => 0,
            'payload' => $payload,
            'queued_at' => now(),
            'sending_at' => now(),
        ]);

        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.smtplw.com.br/v1'), '/');
        $timeout = (int) ($config['timeout'] ?? 15);
        $retryTimes = (int) ($config['retry_times'] ?? 3);
        $retrySleep = (int) ($config['retry_sleep'] ?? 500);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->retry($retryTimes, $retrySleep, throw: false)
                ->withHeaders(['x-auth-token' => $token])
                ->post($baseUrl.'/messages', $payload);

            $json = $response->json();

            $log->fill([
                'attempts' => $retryTimes,
                'last_http_status' => $response->status(),
                'response' => is_array($json) ? $json : ['raw' => $response->body()],
            ]);

            if ($response->successful() || $response->status() === 201) {
                $externalId = data_get($json, 'id')
                    ?? data_get($json, 'message.id')
                    ?? data_get($json, 'data.id');

                $log->status = 'sent';
                $log->external_id = is_scalar($externalId) ? (string) $externalId : null;
                $log->sent_at = now();
                $log->save();

                return;
            }

            $log->status = 'failed';
            $log->failed_at = now();
            $log->last_error = sprintf('KingHost API HTTP %d', $response->status());
            $log->save();

            throw new TransportException(
                sprintf('KingHost API retornou HTTP %d ao enviar e-mail.', $response->status())
            );
        } catch (\Throwable $e) {
            $log->status = 'failed';
            $log->failed_at = now();
            $log->last_error = $e->getMessage();
            $log->save();

            throw $e instanceof TransportException
                ? $e
                : new TransportException('Falha ao enviar email via KingHost: '.$e->getMessage(), 0, $e);
        }
    }

    private function toEmail(RawMessage $rawMessage): Email
    {
        if ($rawMessage instanceof Email) {
            return $rawMessage;
        }

        $headers = $rawMessage->getHeaders();
        $body = $rawMessage->toString();

        $email = new Email();
        if ($headers instanceof Headers) {
            $subject = $headers->getHeaderBody('Subject');
            if (is_string($subject)) $email->subject($subject);
        }

        return $email->text($body);
    }

    /**
     * @param array<int, Address> $addresses
     */
    private function firstAddress(array $addresses, ?Envelope $envelope): ?string
    {
        if (count($addresses) > 0) {
            return $addresses[0]->getAddress();
        }

        if ($envelope !== null) {
            $recipients = $envelope->getRecipients();
            if (count($recipients) > 0) {
                return $recipients[0]->getAddress();
            }
        }

        return null;
    }

    private function guardMonthlyLimit(int $limit): void
    {
        if ($limit <= 0) return;

        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        $sentCount = TransactionalMailLog::query()
            ->where('provider', 'kinghost_smtp')
            ->where('status', 'sent')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        if ($sentCount >= $limit) {
            throw new TransportException(
                sprintf('Limite mensal de envios atingido (%d/%d).', $sentCount, $limit)
            );
        }
    }
}
