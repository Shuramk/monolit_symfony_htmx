import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['parent', 'product'];
    static values = { products: Object };

    changeParent() {
        this.productTarget.replaceChildren(new Option('Выбрать цвет', '0'));
        for (const product of this.productsValue[this.parentTarget.value] ?? []) {
            const label = document.createElement('template');
            label.innerHTML = product.name;
            this.productTarget.add(new Option(label.content.textContent, String(product.id)));
        }
    }

    outside(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    close() {
        this.element.open = false;
    }
}
