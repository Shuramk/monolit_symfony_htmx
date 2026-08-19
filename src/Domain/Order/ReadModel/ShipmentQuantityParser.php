<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

final class ShipmentQuantityParser
{
    /** @return array<int, int> Quantities scaled to three decimal places. */
    public function parse(string $serializedList): array {
        if (!preg_match("/^a:(?<count>\d+):\{(?<items>.*)\}$/Ds", trim($serializedList), $matches)) {
            return [];
        }

        $items = $matches["items"];
        $offset = 0;
        $quantities = [];
        $itemCount = 0;
        $pattern = "/\G(?:i:(?<integerKey>\d+)|s:\d+:\"(?<stringKey>\d+)\");(?:i:(?<integerValue>-?\d+)|d:(?<decimalValue>-?\d+(?:\.\d+)?)|s:\d+:\"(?<stringValue>-?\d+(?:\.\d+)?)\");/A";

        while ($offset < strlen($items)) {
            if (!preg_match($pattern, $items, $item, 0, $offset)) {
                return [];
            }

            $productId = (int) ($item["integerKey"] !== "" ? $item["integerKey"] : $item["stringKey"]);
            $quantity = $item["integerValue"] !== "" ? $item["integerValue"] : ($item["decimalValue"] !== "" ? $item["decimalValue"] : $item["stringValue"]);
            $quantities[$productId] = ($quantities[$productId] ?? 0) + OrderDecimal::toScaled($quantity, 3);
            $offset += strlen($item[0]);
            ++$itemCount;
        }

        return $itemCount === (int) $matches["count"] ? $quantities : [];
    }
}
