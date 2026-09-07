<?php

namespace App\Controller\Admin;

use App\Repository\AssignmentRepository;
use App\Repository\EditionSettingsRepository;
use App\Repository\MessageRepository;
use App\Repository\ParticipantRepository;
use App\Repository\WishRepository;
use App\Service\EditionLockGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(
        ParticipantRepository $participants,
        WishRepository $wishes,
        MessageRepository $messages,
        AssignmentRepository $assignments,
        EditionSettingsRepository $settingsRepository,
        EditionLockGuard $lockGuard,
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'participantCount' => $participants->count([]),
            'wishCount' => $wishes->countAll(),
            'unreadMessages' => $messages->countUnread(),
            'drawActive' => $assignments->hasActiveDraw(),
            'locked' => $lockGuard->isLocked(),
            'settings' => $settingsRepository->getSettings(),
        ]);
    }
}
