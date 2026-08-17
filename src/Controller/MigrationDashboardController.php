<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MigrationDashboardController extends AbstractController
{
    #[Route('/', name: 'migration_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('migration/dashboard.html.twig');
    }

    #[Route('/migration/status', name: 'migration_status', methods: ['GET'])]
    public function status(): Response
    {
        return $this->render('migration/_status.html.twig', [
            'checkedAt' => new \DateTimeImmutable(),
        ]);
    }
}
