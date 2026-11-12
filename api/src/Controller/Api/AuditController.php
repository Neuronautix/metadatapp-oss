<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Security\Roles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/audit', name: 'api_audit_')]
#[IsGranted(Roles::ROLE_USER)]
final class AuditController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', '1'));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', '20')));

        return $this->json([
            'data' => [],
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => 0,
        ]);
    }
}
