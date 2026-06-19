<?php

namespace App\Monitoring\Infrastructure\External;

use App\Monitoring\Domain\Service\NotificationSenderInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Predis\Client as Redis;


class SymfonyNotificationSender implements NotificationSenderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private MailerInterface $mailer,
        private string $discordWebhookUrl,
        private string $slackWebhookUrl,
        private string $adminEmailAddress,
        private Redis $redis
    ) {} 

    public function sendIncidentAlert(int $incidentId, int $monitorId, string $message): void
    {
        $this->sendSlackMessage(sprintf(
            "*INCIDENT CREATED* \n*Incident ID:* %d\n*Monitor ID:* %d\n*Reason:* %s",
            $incidentId,
            $monitorId,
            $message
        ));
        $this->sendEmailAlert(
            subject: sprintf('Alert: Incident #%d Created for Monitor #%d', $incidentId, $monitorId),
            body: sprintf("An incident has been opened.\n\nMonitor ID: %d\nDetails: %s", $monitorId, $message)
        );
    }

    public function sendResolutionAlert(int $incidentId, int $monitorId): void
    {
        $this->sendSlackMessage(sprintf(
            "*INCIDENT RESOLVED* \n*Incident ID:* %d\n*Monitor ID:* %d\nStatus has returned to normal operations.",
            $incidentId,
            $monitorId
        ));
        $this->sendEmailAlert(
            subject: sprintf('Resolved: Incident #%d Closed for Monitor #%d', $incidentId, $monitorId),
            body: sprintf("System recovered cleanly.\n\nIncident ID: %d\nMonitor ID: %d", $incidentId, $monitorId)
        );
    }

    public function sendEscalationAlert(int $incidentId, int $monitorId, string $channel, string $message): void
    {
        $payload = sprintf("*ESCALATION STAGE ALERT* \n*Incident:* #%d | *Monitor:* #%d\n%s", $incidentId, $monitorId, $message);
        match (strtolower($channel)) {
            'slack' => $this->sendSlackMessage($payload),
            'discord' => $this->sendDiscordMessage($payload),
            'email' => $this->sendEmailAlert(sprintf('Escalation Alert for Incident #%d', $incidentId), $payload),
            default => throw new \InvalidArgumentException(sprintf('Unsupported channel "%s"', $channel))
        };
    }

    private function sendSlackMessage(string $markdownText): void
    {
        try {
            $this->httpClient->request('POST', $this->slackWebhookUrl, [
                'json' => ['text' => $markdownText]
            ]);
        } catch (\Exception $e) {
        }
    }

    private function sendEmailAlert(string $subject, string $body): void
    {
        try {
            $email = (new Email())
                ->from('noreply@yourdomain.com')
                ->to($this->adminEmailAddress)
                ->subject($subject)
                ->text($body);

            $this->mailer->send($email);
        } catch (\Exception $e) {
        }
    }

    private function sendDiscordMessage(string $text): void
    {
        try {
            $this->httpClient->request('POST', $this->discordWebhookUrl, [
                'json' => ['content' => $text]
            ]);
        } catch (\Exception $e) {}
    }

    public function tryAcquireNotificationCooldown(int $monitorId, int $cooldownSeconds = 300): bool
    {
        $key = sprintf('monitor:%d:cooldown:notification', $monitorId);
        return (bool) $this->redis->set($key, 'active', ['nx', 'ex' => $cooldownSeconds]);
    }

}
