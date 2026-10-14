<?php declare(strict_types=1);

namespace App\Application\Controller\Identity;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Repository\UserRepository;
use App\Domain\Identity\Repository\UserSettingsRepository;
use App\Domain\Identity\ReadModel\UserPermissionsView;
use App\Domain\Identity\Security\DivisionAccessVoter;
use App\Domain\Identity\UseCases\Dtos\SaveUserDto;
use App\Domain\Identity\UseCases\SaveUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/settings/users')]
#[IsGranted(DivisionAccessVoter::ATTRIBUTE,subject:new Expression('1001'))]
final class UserSettingsController extends AbstractController {
    #[Route('', name: 'user_settings_list', methods: ['GET'])]
    public function list(Request $request, UserSettingsRepository $settings): Response {
        $hideInactive = $request->query->getBoolean('hideInactive', true);
        return $this->render(
            'identity/users.html.twig',
            ['users' => $settings->users($hideInactive), 'roles'=>UserRole::cases(), 'hideInactive'=>$hideInactive],
        );
    }

    #[Route('/new', name: 'user_settings_new', methods: ['GET'])]
    #[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject:new Expression('1002'))]
    #[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject:new Expression('1003'))]
    public function new(UserPermissionsView $permissions): Response {
        return $this->render(
            'identity/user_edit.html.twig',
            ['account' => null, 'roles' => UserRole::cases(), 'permissions' => $permissions->tree(), 'assigned' => []],
        );
    }

    #[Route('/new', name: 'user_settings_create', methods: ['POST'])]
    #[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject:new Expression('1002'))]
    #[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject:new Expression('1003'))]
    public function create(SaveUserDto $dto, SaveUser $save): Response {
        $user = $save->execute(null,$dto);

        return $this->json(['location' => $this->generateUrl('user_settings_edit', ['id' => $user->getId()])]);
    }

    #[Route('/{id}', name: 'user_settings_edit', requirements:['id'=>'\d+'], methods:['GET'])]
    #[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject:new Expression('1002'))]
    public function edit(int $id, UserRepository $users, UserSettingsRepository $settings, UserPermissionsView $permissions): Response {

        return $this->render(
            'identity/user_edit.html.twig',
            [
                'account' => $users->findById($id) ?? throw $this->createNotFoundException('Пользователь не найден.'),
                'roles' => UserRole::cases(),
                'permissions' => $permissions->tree(),
                'assigned' => $settings->assigned($id),
            ],
        );
    }

    #[Route('/{id}', name: 'user_settings_save', requirements:['id'=>'\d+'], methods:['POST'])]
    #[IsGranted(DivisionAccessVoter::ATTRIBUTE, subject:new Expression('1003'))]
    public function save(int $id, SaveUserDto $dto, SaveUser $update): Response {
        if ($dto->password !== null && $dto->password !== '') {
            $this->denyAccessUnlessGranted(DivisionAccessVoter::ATTRIBUTE, 1050);
        }
        $update->execute($id, $dto);

        return $this->json(['location' => $this->generateUrl('user_settings_list')]);
    }
}
