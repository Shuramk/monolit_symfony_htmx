<?php declare(strict_types=1);
namespace App\Domain\Crm\UseCases;

use App\Domain\Crm\Entities\Deal;
use App\Domain\Crm\Repository\CrmRepository;
use App\Domain\Order\UseCases\CreateOrder;
use App\Domain\Order\UseCases\Dtos\CreateOrderDto;
use App\Domain\Order\UseCases\Dtos\CreateOrderLineDto;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class DealActions {
    public function __construct(private CrmRepository $repository, private CreateOrder $createOrder) {}
    public function execute(int $id, string $action, ?int $version, int $actor, bool $main, string $comment, int $cancelType, \DateTimeImmutable $now): ?int {
        return $this->repository->transactional(function () use ($id,$action,$version,$actor,$main,$comment,$cancelType,$now): ?int {
            $row = $this->repository->deal($id,true); $deal = Deal::fromRow($row);
            if ($action === 'convert' && $deal->orderId() !== null) return $deal->orderId();
            $this->repository->checkVersion($row,$version); $stamp = $now->format('Y-m-d H:i:s'); $extra = [];
            switch ($action) {
                case 'cancel':
                    $deal->cancel($comment,$cancelType);
                    $reason = trim($comment) ?: match ($cancelType) { 1=>'Дорого',2=>'Нет в наличии',3=>'Долго ждать',4=>'Свяжутся сами',default=>'' };
                    $message = 'Сделка отменена. '.$reason;
                    $extra = ['cancel_comment'=>$reason,'cancel_type'=>$cancelType];
                    $this->repository->contact($id,$actor,0,$stamp,$reason,$stamp);
                    break;
                case 'hide': if (!$main) throw new AccessDeniedException('Скрывать сделку может только Главная.'); $deal->hide(); $message = 'Сделка скрыта.'; break;
                case 'restore': if (!$main) throw new AccessDeniedException('Восстанавливать сделку может только Главная.'); $deal->restore(); $message = 'Сделка восстановлена.'; break;
                case 'convert':
                    $deal->assertEditable();
                    if (!$row['customer_id'] || !$row['lines']) throw new \DomainException('Для заказа нужны клиент и продукция.');
                    $lines = array_map(static fn ($line): CreateOrderLineDto => new CreateOrderLineDto((int)$line['product_id'],$line['quantity'],$line['price']),$row['lines']);
                    $order = $this->createOrder->execute(new CreateOrderDto((int)$row['customer_id'],new \DateTimeImmutable($row['desired_at'] ?: $now->format('Y-m-d')),$row['shipping_address'],trim($row['comment']."\n".$row['director_comment']),'0',(int)$row['markup'],$lines),$actor,$now);
                    $deal->converted($order); $message = 'Создан заказ №'.$order.'.'; $this->repository->orderHistory($order,$this->repository->historyFor('deal',$id)); break;
                default: throw new \DomainException('Неизвестное действие.');
            }
            $this->repository->updateDeal($id,['state'=>$deal->state(),'converted_order_id'=>$deal->orderId()],(int)$row['version']);
            $this->repository->history('deal',$id,$actor,[$message],$stamp,$extra);
            return $deal->orderId();
        });
    }
}
