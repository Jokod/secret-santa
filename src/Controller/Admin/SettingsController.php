<?php

namespace App\Controller\Admin;

use App\Exception\EditionLockedException;
use App\Form\EditionSettingsType;
use App\Repository\EditionSettingsRepository;
use App\Service\EditionLockGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/settings')]
final class SettingsController extends AbstractController
{
    #[Route('', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EditionSettingsRepository $settingsRepository,
        EntityManagerInterface $em,
        EditionLockGuard $lockGuard,
    ): Response {
        $settings = $settingsRepository->getSettings();
        $locked = $lockGuard->isLocked();
        $form = $this->createForm(EditionSettingsType::class, $settings, ['locked' => $locked]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $lockGuard->assertMutable();
            } catch (EditionLockedException $e) {
                $this->addFlash('danger', $e->getMessage());

                return $this->redirectToRoute('admin_settings');
            }

            $em->flush();
            $this->addFlash('success', 'Configuration enregistrée.');

            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings/index.html.twig', [
            'form' => $form,
            'locked' => $locked,
            'settings' => $settings,
        ]);
    }
}
