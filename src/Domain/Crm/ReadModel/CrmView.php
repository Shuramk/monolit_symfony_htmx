<?php declare(strict_types=1);
namespace App\Domain\Crm\ReadModel;

use App\Domain\Crm\Repository\CrmRepository;
use App\Domain\Order\ReadModel\LegacyProductText;

final readonly class CrmView {
    public const SOURCES = [1=>'Флаер',2=>'Бигборд',3=>'Поисковик/сайт',4=>'Соц. сети',5=>'По совету'];
    public const CANCEL_TYPES = [1=>'Дорого',2=>'Нет в наличии',3=>'Долго ждать',4=>'Свяжутся сами'];
    public function __construct(private CrmRepository $repository, private LegacyProductText $productText) {}
    public function options(): array {
        $products = $this->repository->products();
        $groups = [];
        $byParent = [];
        foreach ($products as &$product) {
            $product['name'] = strip_tags($this->productText->format((string)$product['name']));
            $product['unit'] = strip_tags($this->productText->format((string)$product['unit']));
            $product['group_name'] = strip_tags($this->productText->format((string)$product['group_name']));
            $product['variant_name'] = strip_tags($this->productText->format((string)$product['variant_name']));
            $parent = (int)$product['parent_id'];
            $groups[$parent] = ['id'=>$parent,'name'=>$product['group_name']];
            $byParent[$parent][] = ['id'=>$product['id'],'name'=>$product['variant_name'],'unit'=>$product['unit'],'price'=>$product['price']];
        }
        unset($product);

        return ['users'=>$this->repository->users(),'customers'=>$this->repository->customers(),'products'=>$products,'productGroups'=>array_values($groups),'productsByParent'=>$byParent,'sources'=>self::SOURCES,'cancelTypes'=>self::CANCEL_TYPES];
    }
    public function form(?int $id): array {
        $options = $this->options();
        $deal = $id === null ? null : $this->deal($id);
        $groups = array_column($options['productGroups'],null,'id');
        if ($deal) {
            foreach ($deal['lines'] as &$line) {
                $parent = (int)$line['group_id'];
                if (!isset($groups[$parent])) $groups[$parent] = ['id'=>$parent,'name'=>strip_tags($this->productText->format((string)$line['group_name'])).' (отключён)'];
                $variants = $options['productsByParent'][$parent] ?? [];
                if (!in_array((int)$line['product_id'],array_map('intval',array_column($variants,'id')),true)) {
                    $variants[] = ['id'=>$line['product_id'],'name'=>strip_tags($this->productText->format((string)$line['variant_name'])).' (отключён)','unit'=>strip_tags($this->productText->format((string)$line['unit'])),'price'=>$line['catalog_price'] ?? $line['price']];
                }
                $line['form'] = ['groupId'=>$parent,'productId'=>$line['product_id'],'quantity'=>$line['quantity'],'unitPrice'=>$line['price'],'options'=>$variants,'unit'=>strip_tags($this->productText->format((string)$line['unit']))];
            }
            unset($line);
        }
        $options['productGroups'] = array_values($groups);

        return $options+['deal'=>$deal];
    }
    public function deal(int $id): array { $row = $this->repository->deal($id); $row['history'] = $this->repository->historyFor('deal',$id); return $row; }
    public function task(int $id): array { $row = $this->repository->task($id); $row['history'] = $this->repository->historyFor('task',$id); $row['files'] = $this->repository->files($id); return $row; }
}
