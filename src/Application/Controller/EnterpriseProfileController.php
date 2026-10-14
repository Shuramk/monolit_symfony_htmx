<?php declare(strict_types=1);

namespace App\Application\Controller;

use App\Domain\Enterprise\Exception\EnterpriseNotFound;
use App\Domain\Enterprise\ReadModel\EnterpriseView;
use App\Domain\Enterprise\ReadModel\EnterpriseWarrantyView;
use App\Domain\Identity\Security\DivisionAccessVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EnterpriseProfileController extends AbstractController {
    #[Route("/enterprise", name: "enterprise_profile", methods: ["GET"])]
    public function __invoke(EnterpriseView $enterpriseView): Response {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 1030);
        try {
            $profile = $enterpriseView->getById(1);
        } catch (EnterpriseNotFound) {
            throw $this->createNotFoundException();
        }

        return $this->render("enterprise/profile.html.twig", ["enterprise" => $profile]);
    }

    #[Route("/enterprise/phones", name: "enterprise_phones", methods: ["GET"])]
    public function phones(EnterpriseView $enterpriseView): Response {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 1030);
        try {
            $phones = $enterpriseView->getById(1)->phones;
        } catch (EnterpriseNotFound) {
            throw $this->createNotFoundException();
        }

        return $this->render("enterprise/_phones.html.twig", ["phones" => $phones]);
    }

    #[Route("/enterprise/warranty", name: "enterprise_warranty", methods: ["GET"])]
    public function warranty(EnterpriseWarrantyView $enterpriseWarrantyView): Response {
        $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 1030);
        try {
            $warranty = $enterpriseWarrantyView->getByEnterpriseId(1);
        } catch (EnterpriseNotFound) {
            throw $this->createNotFoundException();
        }

        return $this->render("enterprise/warranty.html.twig", ["warranty" => $warranty]);
    }
}
