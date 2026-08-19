<?php declare(strict_types=1);
namespace App\Application\Controller\Crm;

use App\Domain\Crm\UseCases\TaskFiles;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Security\DivisionAccessVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class TaskFilesController extends AbstractController {
    #[Route('/crm/tasks/{id}/files', name: 'crm_task_upload', requirements: ['id'=>'\d+'], methods: ['POST'])]
    public function upload(int $id, Request $request, TaskFiles $files, User $user): Response {
        $director = $this->access();
        $files->upload(
            $id,
            $request->files->all('files'),
            $request->request->has('version') ? $request->request->getInt('version') : null,
            $user->getId(),
            $director
        );

        return $this->redirectToRoute(
            $director && !$this->isGranted(DivisionAccessVoter::ATTRIBUTE,1750)
                ? 'crm_director_task_show'
                : 'crm_task_show', ['id' => $id]
        );
    }

    #[Route('/crm/tasks/{id}/files/{file}', name: 'crm_task_download', requirements: ['id'=>'\d+', 'file'=>'\d+'], methods: ['GET'])]
    public function download(int $id, int $file, TaskFiles $files, User $user): BinaryFileResponse {
        $entry = $files->download($id, $file, $user->getId(), $this->access());
        $response = $this->file($entry['path'], $entry['name'],ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('Cache-Control','private, no-store');

        return $response;
    }

    #[Route('/crm/tasks/{id}/files/{file}', name: 'crm_task_file_remove', requirements: ['id'=>'\d+', 'file'=>'\d+'], methods: ['POST'])]
    public function remove(int $id, int $file, Request $request, TaskFiles $files, User $user): Response {
        $director = $this->access();
        $data = $request->getPayload();
        $files->remove($id, $file, $data->has('version') ? $data->getInt('version') : null, $user->getId(), $director);

        return $this->json([
            'location' => $this->generateUrl(
                $director && !$this->isGranted(DivisionAccessVoter::ATTRIBUTE,1750) ? 'crm_director_task_show' : 'crm_task_show',
                ['id' => $id],
            )
        ]);
    }

    private function access(): bool {
        $director = $this->isGranted(DivisionAccessVoter::ATTRIBUTE,1755);
        if (!$director) {
            $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE,1750);
        }

        return $director;
    }
}
