<?php declare(strict_types=1);

namespace App\Application\Controller\Crm;

use App\Domain\Crm\ReadModel\DealDocument;
use App\Domain\Identity\Security\DivisionAccessVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(DivisionAccessVoter::ATTRIBUTE,subject:new Expression('1700'))]
final class DealDocumentsController extends AbstractController {
    #[Route('/crm/deals/{id}/documents/{kind}', name: 'crm_deal_document', requirements: ['id'=>'\d+','kind'=>'invoice|offer'], methods: ['GET'])]
    public function download(int $id, string $kind, DealDocument $document): Response {
        $contents = $document->generate($id, $kind, new \DateTimeImmutable('now', new \DateTimeZone('Europe/Kyiv')));

        return new Response($contents,200,[
            'Content-Type'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition'=>HeaderUtils::makeDisposition('attachment','deal-' . $id . '-' . $kind . '.docx'),
            'Cache-Control'=>'private, no-store',
        ]);
    }
}
