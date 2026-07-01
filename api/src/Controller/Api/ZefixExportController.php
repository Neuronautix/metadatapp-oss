<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Zefix\Exports\ZefixExportService;
use App\Zefix\Support\ZefixPermissionChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ZefixExportController extends AbstractController
{
    public function __construct(
        private readonly ZefixExportService $exportService,
        private readonly ZefixPermissionChecker $permissionChecker,
    ) {
    }

    #[Route(path: '/zefix/exports/pool-occupancy.csv', name: 'api_zefix_export_pool_occupancy', methods: ['GET'])]
    public function poolOccupancy(Request $request): Response
    {
        return $this->exportForCurrentUser(fn (User $user) => $this->exportService->exportPoolOccupancy($user, $request->query->all()));
    }

    #[Route(path: '/zefix/exports/batch-history.csv', name: 'api_zefix_export_batch_history', methods: ['GET'])]
    public function batchHistory(Request $request): Response
    {
        return $this->exportForCurrentUser(fn (User $user) => $this->exportService->exportBatchHistory($user, $request->query->all()));
    }

    #[Route(path: '/zefix/exports/line-statistics.csv', name: 'api_zefix_export_line_statistics', methods: ['GET'])]
    public function lineStatistics(Request $request): Response
    {
        return $this->exportForCurrentUser(fn (User $user) => $this->exportService->exportLineStatistics($user, $request->query->all()));
    }

    #[Route(path: '/zefix/exports/mortality-logs.csv', name: 'api_zefix_export_mortality_logs', methods: ['GET'])]
    public function mortalityLogs(Request $request): Response
    {
        return $this->exportForCurrentUser(fn (User $user) => $this->exportService->exportMortalityLogs($user, $request->query->all()));
    }

    #[Route(path: '/zefix/exports/cryo-inventory.csv', name: 'api_zefix_export_cryo_inventory', methods: ['GET'])]
    public function cryoInventory(Request $request): Response
    {
        return $this->exportForCurrentUser(fn (User $user) => $this->exportService->exportCryoInventory($user, $request->query->all()));
    }

    #[Route(path: '/zefix/reports/lines/{lineID}.pdf', name: 'api_zefix_line_report_pdf', methods: ['GET'])]
    public function lineReport(string $lineID): Response
    {
        return $this->exportForCurrentUser(fn (User $user) => $this->exportService->exportLineReport($user, $lineID));
    }

    private function exportForCurrentUser(callable $callback): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $this->permissionChecker->assertCanEdit($user);

        try {
            return $callback($user);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'message' => 'Invalid export request.',
                'detail' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
