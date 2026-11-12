<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Fair3rDemoCsvCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class Fair3rDemoCsvController extends AbstractController
{
    public function __construct(
        private readonly Fair3rDemoCsvCatalog $catalog,
    ) {
    }

    #[Route('/fair3r/demo-results/{key}.csv', name: 'api_fair3r_demo_result_csv', methods: ['GET'])]
    public function csv(string $key): Response
    {
        $record = $this->catalog->get($key);
        if (null === $record) {
            return new Response('Unknown FAIR3R demo CSV.', Response::HTTP_NOT_FOUND);
        }

        $response = new Response($record['csv']);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', \sprintf('inline; filename="%s"', $record['filename']));

        return $response;
    }
}
