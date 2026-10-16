<?php declare(strict_types=1);

namespace App\Domain\Order\ReadModel;

use App\Domain\Common\ValueObjects\Decimal;
use App\Domain\Customer\ReadModel\CustomerChoices;
use App\Domain\Order\ReadModel\Dtos\OrderWizardDto;
use App\Domain\Order\Services\OrderDraftSession;
use App\Domain\Product\Repository\ProductCatalogRepository;
use App\Domain\Warehouse\Repository\ShippingRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class OrderWizardView {
    public function __construct(
        private OrderDraftSession $draft,
        private ProductCatalogRepository $products,
        private CustomerChoices $customers,
        private ShippingRepository $shipping,
        private OrderFinancialCalculator $calculator,
        private LegacyProductText $text,
    ) {}

    public function get(): ?OrderWizardDto {
        if (!$this->draft->hasLines()) {

            return null;
        }
        $draft = $this->draft->load();
        $products = array_column($this->products->orderProducts(array_column($draft['lines'], 'productId')), null, 'id');
        $lines = [];
        $financialLines = [];
        $weight = BigDecimal::zero();
        foreach ($draft['lines'] as $input) {
            $product = $products[$input['productId']] ?? throw new \DomainException('Товар черновика недоступен. Вернитесь к выбору продукции.');
            $price = Decimal::fromString($input['unitPrice'] ?? $product['price'], 4);
            $discount = Decimal::fromString($input['discount'], 2);
            $effectivePrice = $price->multiply(Decimal::fromScaled(10000 - $discount->scaledValue(), 2))->divideByInt(100)->toScale(4);
            $financialLines[] = ['Quantity' => $input['quantity'], 'newPrice' => $effectivePrice->format()];
            $weight = $weight->plus(BigDecimal::of($product['weight_per_unit'] ?? '0')->multipliedBy($input['quantity'])->multipliedBy('0.001'));
            $lines[] = [
                'productId' => (int) $product['id'], 'groupId' => (int) $product['parent_id'],
                'groupName' => $this->text->format($product['group_name']),
                'variantName' => $this->text->format($product['name']),
                'unit' => $this->text->format($product['unit'] ?? ''),
                'quantity' => $input['quantity'], 'unitPrice' => $price->format(), 'discount' => $discount->format(),
            ];
        }
        $financial = $this->calculator->calculate($financialLines, '0', (int) $draft['markup'], '0', '0');
        foreach ($lines as $index => &$line) {
            $line['total'] = Decimal::fromScaled($financial->lines[$index]['total'], 2)->format();
        }
        unset($line);

        return new OrderWizardDto(
            $lines, (int) $draft['markup'], Decimal::fromScaled($financial->goodsTotal, 2)->format(),
            Decimal::fromScaled($financial->total, 2)->format(), (string) $weight->toScale(3, RoundingMode::HalfUp),
            isset($draft['customerId']) ? $this->customers->find((int) $draft['customerId']) : null,
            $this->shipping->carriers(),
            bin2hex(random_bytes(16)),
        );
    }
}
