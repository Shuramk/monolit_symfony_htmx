<?php declare(strict_types=1);
namespace App\Domain\Crm\UseCases;

use App\Domain\Crm\Entities\Deal;
use App\Domain\Crm\ReadModel\CrmHistoryText;
use App\Domain\Crm\Repository\CrmRepository;
use App\Domain\Crm\UseCases\Dtos\SaveDealDto;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class SaveDeal {
    public function __construct(private CrmRepository $repository, private CrmHistoryText $historyText) {}
    public function execute(?int $id, SaveDealDto $data, int $actor, bool $main, \DateTimeImmutable $now): int {
        return $this->repository->transactional(function () use ($id,$data,$actor,$main,$now): int {
            $old = $id === null ? null : $this->repository->deal($id,true);
            if ($old) { $this->repository->checkVersion($old,$data->version); Deal::fromRow($old)->assertEditable(); }
            $this->repository->requireCustomer($data->customerId);
            if ($data->stackerId) $this->repository->requireCustomer($data->stackerId);
            $director = trim($data->directorComment);
            if (!$main && $director !== ($old['director_comment'] ?? '')) throw new AccessDeniedException('Комментарий руководителя может менять только Главная.');
            $budget = Deal::decimal(trim($data->budget) === '' ? '0' : $data->budget,2); $lines = []; $seen = [];
            if ($data->lines) $budget = \App\Domain\Common\ValueObjects\Decimal::zero(2);
            foreach ($data->lines as $line) {
                if (!is_array($line)) throw new \DomainException('Некорректная строка продукции.');
                $product = filter_var($line['productId'] ?? null,FILTER_VALIDATE_INT);
                if (!$product || $product <= 0 || isset($seen[$product])) throw new \DomainException('Выберите товар без повторов.');
                $seen[$product] = true;
                $existing = in_array($product,array_map('intval',array_column($old['lines'] ?? [],'product_id')),true);
                $this->repository->requireProduct($product,$existing);
                $quantity = Deal::decimal((string)($line['quantity'] ?? ''),3);
                $price = Deal::decimal((string)($line['unitPrice'] ?? $line['price'] ?? ''),4);
                if (array_key_exists('unitPrice',$line)) {
                    $discountText = str_replace(',','.',trim((string)($line['discount'] ?? '0')));
                    if (!preg_match('/^-?\d{1,3}(?:\.\d{1,6})?$/D',$discountText)) throw new \DomainException('Некорректная скидка.');
                    $discount = \App\Domain\Common\ValueObjects\Decimal::fromString($discountText,6);
                    if (abs($discount->scaledValue()) > 100000000) throw new \DomainException('Скидка должна быть от -100 до 100%.');
                    $netPrice = BigDecimal::of($price->format())->multipliedBy(BigDecimal::of('100')->minus($discountText))->dividedBy('100',4,RoundingMode::HalfUp);
                    $price = Deal::decimal((string)$netPrice,4);
                }
                if ($quantity->isZero()) throw new \DomainException('Количество должно быть больше нуля.');
                $budget = $budget->add($quantity->multiply($price));
                $lines[] = ['product_id'=>$product,'quantity'=>$quantity->format(),'price'=>$price->format()];
            }
            if ($data->lines) $budget = $budget->multiplyByInt(100+$data->markup)->divideByInt(100)->toScale(2);
            if ($budget->scaledValue() > 999999999999) throw new \DomainException('Бюджет слишком большой.');
            $due = Deal::date($data->contactAt);
            if ($due === null || !$data->executorId) throw new \DomainException('Укажите дату контакта и исполнителя.');
            $this->repository->requireUser($data->executorId,isset($old['contact']['executor_id']) ? (int)$old['contact']['executor_id'] : null);
            $row = ['customer_id'=>$data->customerId,'comment'=>trim($data->comment),'budget'=>$budget->format(2),'markup'=>$data->markup,
                'director_comment'=>$director,'desired_at'=>Deal::date($data->desiredAt,true),'shipping_address'=>trim($data->shippingAddress ?? '') ?: null,'stacker_id'=>$data->stackerId ?: null,'source_id'=>$data->sourceId ?: null];
            $stamp = $now->format('Y-m-d H:i:s'); $changes = [];
            if ($id === null) { $id = $this->repository->createDeal($row+['author_id'=>$actor,'created_at'=>$stamp]); $changes[] = 'Создана сделка №'.$id; }
            else {
                $titles = ['customer_id'=>'Клиент','comment'=>'Комментарий','budget'=>'Бюджет','markup'=>'БН/ФОП','director_comment'=>'Комментарий руководителя','desired_at'=>'Дата доставки','shipping_address'=>'Адрес','stacker_id'=>'Укладчик','source_id'=>'Источник клиента'];
                foreach ($row as $field=>$value) if ((string)$value !== (string)$old[$field]) $changes[] = $titles[$field].': '.$old[$field].' → '.$value;
                $this->repository->updateDeal($id,$row,(int)$old['version']);
            }
            $oldLines = array_map(static fn ($r): array => ['product_id'=>(int)$r['product_id'],'quantity'=>$r['quantity'],'price'=>$r['price']],$old['lines'] ?? []);
            if ($oldLines !== $lines) { $changes[] = 'Продукция: '.$this->historyText->products($lines); $this->repository->replaceItems($id,$lines); }
            $contact = $old['contact'] ?? null;
            if (!$contact || (int)$contact['executor_id'] !== $data->executorId || $contact['due_at'] !== $due || $contact['comment'] !== trim($data->contactComment)) {
                $this->repository->contact($id,$actor,$data->executorId,$due,trim($data->contactComment),$stamp);
                $changes[] = 'Следующий контакт: '.$due.', исполнитель №'.$data->executorId.'. '.trim($data->contactComment);
            }
            $this->repository->history('deal',$id,$actor,$changes,$stamp);
            return $id;
        });
    }
}
