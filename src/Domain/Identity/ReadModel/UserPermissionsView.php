<?php declare(strict_types=1);

namespace App\Domain\Identity\ReadModel;

use App\Domain\Identity\Repository\UserSettingsRepository;

final readonly class UserPermissionsView {
    public function __construct(private UserSettingsRepository $settings) {}
    public function tree(): array {
        $rows = [];
        foreach ($this->settings->permissions() as $row) {
            $id = (int)$row['legacy_division_id'];
            $rows[$id] = ['id'=>$id,'title'=>$row['title'],'parent'=>(int)($row['sub_parent_id'] ?: $row['parent_id']),'enabled'=>(bool)$row['enabled']];
        }
        foreach ($rows as $id=>&$row) {
            if ($row['parent'] === $id || !isset($rows[$row['parent']])) $row['parent'] = 0;
            $seen = [$id=>true]; $parent = $row['parent'];
            while ($parent !== 0 && isset($rows[$parent])) {
                if (isset($seen[$parent])) { $row['parent'] = 0; break; }
                $seen[$parent] = true; $parent = $rows[$parent]['parent'];
            }
        }
        unset($row);
        $byParent = [];
        foreach ($rows as $row) $byParent[$row['parent']][] = $row;
        $build = static function (int $parent) use (&$build,$byParent): array {
            return array_map(static fn (array $row): array => $row + ['children'=>$build($row['id'])],$byParent[$parent] ?? []);
        };
        return $build(0);
    }
}
