<?php

namespace App\Monitoring\Infrastructure\External;

use App\Monitoring\Domain\Service\NotificationSenderInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyNotificationSender implements NotificationSenderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private MailerInterface $mailer,
        private string $slackWebhookUrl,
        private string $adminEmailAddress
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
}
