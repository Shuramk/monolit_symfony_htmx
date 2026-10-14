# Decimal

`App\Domain\Common\ValueObjects\Decimal` — immutable value object для точных десятичных значений без `float`.

Он хранит целое масштабированное значение и scale. Создание: `Decimal::fromString("330.0000", 4)`. Основные операции: `add`, `subtract`, `multiply`, `multiplyByInt`, `divideByInt`, `round`, `toScale`, `format`.

Округление выполняется до ближайшего значения, половина — от нуля. Цена, количество, скидка и денежные суммы приходят из БД/HTML как строки и переходят в `Decimal` только внутри расчётов.

`OrderDecimal` оставлен временным deprecated-адаптером для не перенесённых read-model, но всю арифметику делегирует `Decimal`.
