<?php

declare(strict_types=1);

namespace App\Domain\Identity\Import;

final class LegacyPermissionExpander {
    private const CONNECTED = [230 => [231], 2001 => [2004], 501 => [502, 503, 504, 507], 290 => [291, 292], 1150 => [1151, 1152], 601 => [602], 232 => [233], 226 => [234, 240, 210, 211, 243], 1030 => [1051, 1052, 1053], 313 => [314, 315, 316], 320 => [321, 322, 324], 330 => [331], 260 => [261], 263 => [264], 140 => [141], 120 => [126], 122 => [123], 203 => [204], 114 => [115], 116 => [117, 118], 280 => [281], 295 => [296], 160 => [161], 1520 => [1521, 1522], 1700 => [1701, 1702, 1703, 1704], 5900 => [5900, 5911], 1720 => [1725], 1750 => [1751, 1752, 1753], 1755 => [1756, 1757, 1758], 3501 => [3502]];

    /** @return list<int> */
    public function expand(?string $permissions): array {
        $result = [];
        foreach (explode(";", (string) $permissions) as $permission) {
            if (!ctype_digit($permission)) continue;
            $id = (int) $permission;
            $result[$id] = $id;
            foreach (self::CONNECTED[$id] ?? [] as $connectedId) $result[$connectedId] = $connectedId;
        }
        return array_values($result);
    }
}
