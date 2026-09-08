<?php

namespace App\Service;

use App\Entity\Assignment;
use App\Entity\EditionSettings;
use App\Entity\Message;
use App\Entity\Participant;
use App\Repository\EditionSettingsRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AppMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EditionSettingsRepository $settingsRepository,
        private readonly string $mailFrom,
        private readonly string $defaultUri,
    ) {
    }

    public function sendWelcome(Participant $participant): void
    {
        $settings = $this->settingsRepository->getSettings();
        $link = $this->participantLink($participant);
        $budget = $this->formatBudget($settings);
        $body = $this->renderTemplate($settings->getWelcomeEmailTemplate(), $participant->getName(), null, $budget, $link);

        $this->send(
            $participant->getEmail(),
            'Bienvenue au Secret Santa',
            $body,
            $link,
            'Bienvenue !',
            'Ton espace personnel est prêt',
            'Accéder à mon espace',
            [$participant->getName(), $budget],
        );
    }

    public function sendDrawResult(Assignment $assignment): void
    {
        $settings = $this->settingsRepository->getSettings();
        $santa = $assignment->getSanta();
        $target = $assignment->getTarget();
        if ($santa === null || $target === null) {
            return;
        }

        $link = $this->participantLink($santa);
        $budget = $this->formatBudget($settings);
        $body = $this->renderTemplate(
            $settings->getResultEmailTemplate(),
            $santa->getName(),
            $target->getName(),
            $budget,
            $link
        );

        $this->send(
            $santa->getEmail(),
            'Résultat du tirage Secret Santa',
            $body,
            $link,
            'Tirage effectué',
            'Ta mission Secret Santa est prête',
            'Voir mon espace',
            [$santa->getName(), $target->getName(), $budget],
        );
    }

    public function sendWishReminder(Participant $participant): void
    {
        $settings = $this->settingsRepository->getSettings();
        $link = $this->participantLink($participant);
        $budget = $this->formatBudget($settings);
        $body = $this->renderTemplate($settings->getReminderEmailTemplate(), $participant->getName(), null, $budget, $link);

        $this->send(
            $participant->getEmail(),
            'Rappel : ta liste de souhaits',
            $body,
            $link,
            'Rappel souhaits',
            'Aide ton Santa à te faire plaisir',
            'Compléter ma liste',
            [$participant->getName(), $budget],
        );
    }

    public function sendMessageNotification(Message $message, Participant $recipient): void
    {
        $link = $this->participantLink($recipient);
        $body = sprintf(
            "Tu as reçu un nouveau message Secret Santa.\nOuvre ton espace pour le lire et répondre :\n%s",
            $link
        );

        $this->send(
            $recipient->getEmail(),
            'Nouveau message Secret Santa',
            $body,
            $link,
            'Nouveau message',
            'Un échange t’attend dans ton espace',
            'Lire le message',
            [$recipient->getName()],
        );
    }

    private function participantLink(Participant $participant): string
    {
        $path = $this->urlGenerator->generate(
            'participant_home',
            ['token' => $participant->getTokenSecret()],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );

        return rtrim($this->defaultUri, '/').$path;
    }

    private function formatBudget(EditionSettings $settings): string
    {
        return rtrim(rtrim(number_format($settings->getBudgetMax(), 2, '.', ''), '0'), '.') ?: '0';
    }

    private function renderTemplate(
        string $template,
        string $santaName,
        ?string $targetName,
        string $budget,
        string $link,
    ): string {
        return strtr($template, [
            '{SANTA}' => $santaName,
            '{TARGET}' => $targetName ?? '',
            '{BUDGET}' => $budget,
            '{LINK}' => $link,
        ]);
    }

    /**
     * @param list<string> $highlights
     */
    private function toHtmlBody(string $textBody, string $link, string $ctaLabel, array $highlights): string
    {
        $html = nl2br(htmlspecialchars($textBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $escapedLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $linkToken = '___SANTA_LINK___';
        $html = str_replace($escapedLink, $linkToken, $html);

        $unique = [];
        foreach ($highlights as $value) {
            $value = trim($value);
            if ($value !== '') {
                $unique[$value] = mb_strlen($value);
            }
        }
        arsort($unique);

        foreach (array_keys($unique) as $value) {
            $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = str_replace($escaped, '<strong>'.$escaped.'</strong>', $html);
        }

        $anchor = sprintf(
            '<a href="%s" style="color:#8b1e2d;font-weight:700;text-decoration:underline;">%s</a>',
            $escapedLink,
            htmlspecialchars($ctaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );

        return str_replace($linkToken, $anchor, $html);
    }

    /**
     * @param list<string> $highlights
     */
    private function send(
        string $to,
        string $subject,
        string $body,
        string $link,
        string $heading,
        string $subheading,
        string $ctaLabel,
        array $highlights,
    ): void {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Secret Santa Familial'))
            ->to($to)
            ->subject($subject)
            ->text($body)
            ->htmlTemplate('emails/generic.html.twig')
            ->context([
                'subject' => $subject,
                'heading' => $heading,
                'subheading' => $subheading,
                'body' => $this->toHtmlBody($body, $link, $ctaLabel, $highlights),
            ]);

        $this->mailer->send($email);
    }
}
