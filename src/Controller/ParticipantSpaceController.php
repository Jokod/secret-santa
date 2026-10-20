<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Participant;
use App\Entity\Wish;
use App\Form\MessageFormType;
use App\Form\WishType;
use App\Repository\EditionSettingsRepository;
use App\Repository\MessageRepository;
use App\Service\ChatLivePublisher;
use App\Service\ChatThreadTopic;
use App\Service\EditionLockGuard;
use App\Service\ElfGameService;
use App\Service\MessageService;
use App\Service\WishBudgetValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/participant/{token}')]
final class ParticipantSpaceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EditionSettingsRepository $settingsRepository,
        private readonly WishBudgetValidator $budgetValidator,
        private readonly EditionLockGuard $lockGuard,
        private readonly MessageService $messageService,
        private readonly MessageRepository $messageRepository,
        private readonly ElfGameService $elfGameService,
        private readonly ChatThreadTopic $chatThreadTopic,
        private readonly ChatLivePublisher $chatLivePublisher,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('', name: 'participant_home', methods: ['GET'])]
    public function home(string $token): Response
    {
        return $this->renderHome($this->requireParticipant($token));
    }

    #[Route('/wishes/new', name: 'participant_wish_new', methods: ['POST'])]
    public function addWish(Request $request, string $token): Response
    {
        $participant = $this->requireParticipant($token);
        $settings = $this->settingsRepository->getSettings();

        $wish = new Wish();
        $form = $this->createForm(WishType::class, $wish, ['budget_max' => $settings->getBudgetMax()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $participant->addWish($wish);
            $this->em->persist($wish);
            $this->em->flush();
            $this->addFlash('success', 'Souhait ajouté.');
            $this->flashOverBudgetWarning($wish->getEstimatedPrice(), $settings->getBudgetMax());

            return $this->redirectToRoute('participant_home', ['token' => $token]);
        }

        return $this->renderHome($participant, wishForm: $form);
    }

    #[Route('/wishes/{id}/edit', name: 'participant_wish_edit', methods: ['GET', 'POST'])]
    public function editWish(Request $request, string $token, Wish $wish): Response
    {
        $participant = $this->requireParticipant($token);
        if ($wish->getParticipant()?->getId() !== $participant->getId()) {
            throw $this->createNotFoundException('Souhait introuvable.');
        }

        $settings = $this->settingsRepository->getSettings();
        $form = $this->createForm(WishType::class, $wish, ['budget_max' => $settings->getBudgetMax()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Souhait modifié.');
            $this->flashOverBudgetWarning($wish->getEstimatedPrice(), $settings->getBudgetMax());

            return $this->redirectToRoute('participant_home', ['token' => $token]);
        }

        return $this->renderHome($participant, wishForm: $form, editingWish: $wish);
    }

    #[Route('/elf-game/start', name: 'participant_elf_game_start', methods: ['POST'])]
    public function startElfGame(Request $request, string $token): JsonResponse
    {
        $participant = $this->requireParticipant($token);
        $settings = $this->settingsRepository->getSettings();

        if (!$settings->isEasterEggEnabled()) {
            return $this->json(['ok' => false, 'error' => 'disabled'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->isCsrfTokenValid('elf_score_'.$participant->getId(), (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')))) {
            return $this->json(['ok' => false, 'error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        $sessionToken = $this->elfGameService->startSession($participant);
        $this->em->flush();

        return $this->json([
            'ok' => true,
            'sessionToken' => $sessionToken,
            'duration' => ElfGameService::DURATION_SECONDS,
        ]);
    }

    #[Route('/elf-score', name: 'participant_elf_score', methods: ['POST'])]
    public function submitElfScore(Request $request, string $token): JsonResponse
    {
        $participant = $this->requireParticipant($token);
        $settings = $this->settingsRepository->getSettings();

        if (!$settings->isEasterEggEnabled()) {
            return $this->json(['ok' => false, 'error' => 'disabled'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->isCsrfTokenValid('elf_score_'.$participant->getId(), (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')))) {
            return $this->json(['ok' => false, 'error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = [
                'score' => $request->request->get('score'),
                'sessionToken' => $request->request->get('sessionToken'),
            ];
        }

        $score = filter_var($payload['score'] ?? null, FILTER_VALIDATE_INT);
        $sessionToken = (string) ($payload['sessionToken'] ?? '');

        if ($score === false || $sessionToken === '') {
            return $this->json(['ok' => false, 'error' => 'invalid'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->elfGameService->submitScore($participant, $sessionToken, $score);
        $this->em->flush();

        if (!$result['ok']) {
            $status = match ($result['error']) {
                'too_early' => Response::HTTP_TOO_EARLY,
                'no_session', 'session', 'expired' => Response::HTTP_CONFLICT,
                default => Response::HTTP_BAD_REQUEST,
            };

            return $this->json($result, $status);
        }

        return $this->json($result);
    }

    #[Route('/wishes/{id}/delete', name: 'participant_wish_delete', methods: ['POST'])]
    public function deleteWish(Request $request, string $token, Wish $wish): Response
    {
        $participant = $this->requireParticipant($token);
        if ($wish->getParticipant()?->getId() !== $participant->getId()) {
            throw $this->createNotFoundException('Souhait introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_wish_'.$wish->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Réessayez.');

            return $this->redirectToRoute('participant_home', ['token' => $token]);
        }

        $this->em->remove($wish);
        $this->em->flush();
        $this->addFlash('success', 'Souhait supprimé.');

        return $this->redirectToRoute('participant_home', ['token' => $token]);
    }

    #[Route('/messages/target', name: 'participant_messages_target', methods: ['GET', 'POST'])]
    public function messagesTarget(Request $request, string $token): Response
    {
        $participant = $this->requireParticipant($token);
        $assignment = $participant->getAssignmentAsSanta();
        $target = $assignment?->getTarget();
        if ($target === null) {
            $this->addFlash('danger', 'Aucun tirage actif pour écrire à ta cible.');

            return $this->redirectToRoute('participant_home', ['token' => $token]);
        }

        $form = $this->createForm(MessageFormType::class, null, ['csrf_token_id' => 'msg_to_target']);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                if ($this->wantsJson($request)) {
                    return $this->json(['error' => 'Message invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                return $this->renderMessagesTarget($participant, $target, $form);
            }

            $message = $this->messageService->sendFromSanta($participant, (string) $form->get('body')->getData());
            if ($this->wantsJson($request)) {
                return $this->json(['message' => $this->chatLivePublisher->payloadFor($message)]);
            }

            $this->addFlash('success', 'Message envoyé.');

            return $this->redirectToRoute('participant_messages_target', ['token' => $token]);
        }

        return $this->renderMessagesTarget($participant, $target, $form);
    }

    #[Route('/messages/santa', name: 'participant_messages_santa', methods: ['GET', 'POST'])]
    public function messagesSanta(Request $request, string $token): Response
    {
        $participant = $this->requireParticipant($token);
        $assignment = $participant->getAssignmentAsTarget();
        $santa = $assignment?->getSanta();
        if ($santa === null) {
            $this->addFlash('danger', 'Aucun tirage actif pour écrire à ton Santa.');

            return $this->redirectToRoute('participant_home', ['token' => $token]);
        }

        $form = $this->createForm(MessageFormType::class, null, ['csrf_token_id' => 'msg_to_santa']);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                if ($this->wantsJson($request)) {
                    return $this->json(['error' => 'Message invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                return $this->renderMessagesSanta($participant, $santa, $form);
            }

            $message = $this->messageService->sendFromTarget($participant, (string) $form->get('body')->getData());
            if ($this->wantsJson($request)) {
                return $this->json(['message' => $this->chatLivePublisher->payloadFor($message)]);
            }

            $this->addFlash('success', 'Message envoyé.');

            return $this->redirectToRoute('participant_messages_santa', ['token' => $token]);
        }

        return $this->renderMessagesSanta($participant, $santa, $form);
    }

    #[Route('/messages/{id}/read', name: 'participant_message_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markMessageRead(Request $request, string $token, Message $message): JsonResponse
    {
        $participant = $this->requireParticipant($token);

        if (!$this->isCsrfTokenValid(
            'msg_read_'.$participant->getId(),
            (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')),
        )) {
            return $this->json(['ok' => false, 'error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->messageService->markReadForRecipient($message, $participant)) {
            return $this->json(['ok' => false, 'error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $this->json(['ok' => true]);
    }

    private function renderHome(Participant $participant, ?FormInterface $wishForm = null, ?Wish $editingWish = null): Response
    {
        $settings = $this->settingsRepository->getSettings();
        $wishForm ??= $this->createForm(WishType::class, new Wish(), ['budget_max' => $settings->getBudgetMax()]);
        $unread = $this->messageRepository->countUnreadForParticipant($participant);

        return $this->render('participant/home.html.twig', [
            'participant' => $participant,
            'target' => $participant->getAssignmentAsSanta()?->getTarget(),
            'settings' => $settings,
            'locked' => $this->lockGuard->isLocked(),
            'wishForm' => $wishForm,
            'editingWish' => $editingWish,
            'unreadFromTarget' => $unread['fromTarget'],
            'unreadFromSanta' => $unread['fromSanta'],
        ]);
    }

    private function renderMessagesTarget(Participant $participant, Participant $target, FormInterface $form): Response
    {
        $thread = $this->messageRepository->findThread($participant, $target);
        $this->messageService->markThreadReadForViewer($thread, $participant);

        $unread = $this->messageRepository->countUnreadForParticipant($participant);

        return $this->render('participant/messages_target.html.twig', [
            'participant' => $participant,
            'target' => $target,
            'settings' => $this->settingsRepository->getSettings(),
            'locked' => $this->lockGuard->isLocked(),
            'thread' => $thread,
            'form' => $form,
            'mercureTopic' => $this->chatThreadTopic->forPair($participant, $target),
            'markReadUrlTemplate' => $this->generateUrl('participant_message_read', [
                'token' => $participant->getTokenSecret(),
                'id' => 0,
            ]),
            'markReadCsrf' => $this->csrfTokenManager->getToken('msg_read_'.$participant->getId())->getValue(),
            'unreadFromTarget' => $unread['fromTarget'],
            'unreadFromSanta' => $unread['fromSanta'],
        ]);
    }

    private function renderMessagesSanta(Participant $participant, Participant $santa, FormInterface $form): Response
    {
        $thread = $this->messageRepository->findThread($santa, $participant);
        $this->messageService->markThreadReadForViewer($thread, $participant);

        $unread = $this->messageRepository->countUnreadForParticipant($participant);

        return $this->render('participant/messages_santa.html.twig', [
            'participant' => $participant,
            'settings' => $this->settingsRepository->getSettings(),
            'locked' => $this->lockGuard->isLocked(),
            'thread' => $thread,
            'form' => $form,
            'mercureTopic' => $this->chatThreadTopic->forPair($santa, $participant),
            'markReadUrlTemplate' => $this->generateUrl('participant_message_read', [
                'token' => $participant->getTokenSecret(),
                'id' => 0,
            ]),
            'markReadCsrf' => $this->csrfTokenManager->getToken('msg_read_'.$participant->getId())->getValue(),
            'unreadFromTarget' => $unread['fromTarget'],
            'unreadFromSanta' => $unread['fromSanta'],
        ]);
    }

    private function flashOverBudgetWarning(float $price, float $budgetMax): void
    {
        if (!$this->budgetValidator->isOverBudget($price, $budgetMax)) {
            return;
        }

        $this->addFlash('warning', sprintf(
            'Attention : ce souhait dépasse le budget de %s €.',
            $this->budgetValidator->formatAmount($budgetMax)
        ));
    }

    private function requireParticipant(string $token): Participant
    {
        $participant = $this->em->getRepository(Participant::class)->findOneBy(['tokenSecret' => $token]);
        if (!$participant instanceof Participant) {
            throw new NotFoundHttpException('Lien participant invalide ou expiré.');
        }

        return $participant;
    }

    private function wantsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains((string) $request->headers->get('Accept', ''), 'application/json');
    }
}
