<?php

namespace App\Controller\Admin;

use App\Exception\DrawException;
use App\Repository\AssignmentRepository;
use App\Repository\ParticipantRepository;
use App\Service\AppMailer;
use App\Service\DrawService;
use App\Service\EditionLockGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/draw')]
final class DrawController extends AbstractController
{
    public function __construct(
        private readonly DrawService $drawService,
        private readonly AssignmentRepository $assignments,
        private readonly ParticipantRepository $participants,
        private readonly AppMailer $mailer,
        private readonly EditionLockGuard $lockGuard,
    ) {
    }

    #[Route('', name: 'admin_draw', methods: ['GET'])]
    public function index(): Response
    {
        $withoutWishes = $this->participants->findWithoutWishes();

        return $this->render('admin/draw/index.html.twig', [
            'assignments' => $this->assignments->findAllWithParticipants(),
            'participantCount' => $this->participants->count([]),
            'locked' => $this->lockGuard->isLocked(),
            'canDraw' => $this->participants->count([]) >= 3 && !$this->lockGuard->isLocked(),
            'participantsWithoutWishes' => $withoutWishes,
            'canRemindMissing' => $withoutWishes !== [],
        ]);
    }

    #[Route('/run', name: 'admin_draw_run', methods: ['POST'])]
    public function run(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('draw_run', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_draw');
        }

        if (!$request->request->getBoolean('confirm')) {
            $this->addFlash('warning', 'Cochez la confirmation pour lancer le tirage (emails envoyés immédiatement).');

            return $this->redirectToRoute('admin_draw');
        }

        $withoutWishes = $this->participants->findWithoutWishes();
        if ($withoutWishes !== [] && !$request->request->getBoolean('confirm_missing_wishes')) {
            $this->addFlash('warning', 'Des participants n’ont pas de souhaits : cochez la confirmation dédiée pour lancer le tirage quand même.');

            return $this->redirectToRoute('admin_draw');
        }

        try {
            $this->drawService->run(true);
            $this->addFlash('success', 'Tirage lancé : les emails de résultat ont été envoyés.');
        } catch (DrawException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_draw');
    }

    #[Route('/reset', name: 'admin_draw_reset', methods: ['POST'])]
    public function reset(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('draw_reset', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_draw');
        }

        if (!$request->request->getBoolean('confirm')) {
            $this->addFlash('warning', 'Cochez la confirmation pour réinitialiser le tirage (irréversible pour cette édition).');

            return $this->redirectToRoute('admin_draw');
        }

        $this->drawService->reset();
        $this->addFlash('success', 'Tirage et messages réinitialisés.');

        return $this->redirectToRoute('admin_draw');
    }

    #[Route('/remind', name: 'admin_draw_remind', methods: ['POST'])]
    public function remind(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('draw_remind', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_draw');
        }

        $withoutWishes = $this->participants->findWithoutWishes();
        if ($withoutWishes === []) {
            $this->addFlash('success', 'Tous les participants ont déjà au moins un souhait.');

            return $this->redirectToRoute('admin_draw');
        }

        foreach ($withoutWishes as $participant) {
            $this->mailer->sendWishReminder($participant);
        }
        $this->addFlash('success', sprintf(
            'Rappel envoyé à %d participant(s) sans souhaits.',
            \count($withoutWishes)
        ));

        return $this->redirectToRoute('admin_draw');
    }
}
