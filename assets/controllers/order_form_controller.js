import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["lines", "template", "total", "totalWithMarkup", "markup", "markupMode", "markupFop", "markupSuffix", "vatColumn", "fopColumn", "budget", "vatAmount", "balance", "totalWeight", "totalsCell"];
    static values = { products: Object, allowEmpty: Boolean };

    connect() {
        this.index = this.linesTarget.querySelectorAll("[data-order-line]").length;
        this.markupChanged();
    }

    add() {
        this.linesTarget.insertAdjacentHTML("beforeend", this.templateTarget.innerHTML.replaceAll("__INDEX__", this.index));
        this.index += 1;
        this.updateMarkupColumns();
        if (this.hasBudgetTarget) this.recalculate();
    }


    groupChanged(event) {
        const line = event.currentTarget.closest("[data-order-line]");
        const productSelect = line.querySelector(`[name*="[productId]"]`);
        const products = this.productsValue[event.currentTarget.value] || [];

        productSelect.innerHTML = "<option value=\"\">Выбрать вариант</option>";
        products.forEach((product) => {
            const option = new Option(product.name, product.id);
            option.dataset.price = product.price;
            option.dataset.unit = product.unit ?? '';
            option.dataset.weight = product.weight_per_unit ?? '0';
            productSelect.add(option);
        });
        productSelect.disabled = products.length === 0;
        line.querySelector(`[name*="[unitPrice]"]`).value = "0.0000";
        line.querySelector(`[name*="[discount]"]`).value = "0.00";
        this.recalculate();
    }

    productChanged(event) {
        const line = event.currentTarget.closest("[data-order-line]");
        line.querySelector(`[name*="[unitPrice]"]`).value = event.currentTarget.selectedOptions[0]?.dataset.price || "0.0000";
        line.querySelector(`[name*="[discount]"]`).value = "0.00";
        this.recalculate();
    }

    vatPriceChanged(event) {
        this.setBasePriceFromSpecialPrice(event.currentTarget, 1.2);
    }

    fopPriceChanged(event) {
        this.setBasePriceFromSpecialPrice(event.currentTarget, 1 + this.effectiveMarkup() / 100);
    }

    markupChanged() {
        this.updateMarkupColumns();
        this.recalculate();
    }

    globalDiscountChanged(event) {
        this.linesTarget.querySelectorAll('[name*="[discount]"]').forEach(input => { input.value = event.currentTarget.value; });
        this.recalculate();
    }

    recalculate() {
        const markup = this.effectiveMarkup();
        const mode = this.markupModeTarget.value;
        let total = 0;
        let totalWeight = 0;

        this.linesTarget.querySelectorAll("[data-order-line]").forEach((line) => {
            const quantity = Number(line.querySelector(`[name*="[quantity]"]`).value || 0);
            const price = Number(line.querySelector(`[name*="[unitPrice]"]`).value || 0);
            const discount = Number(line.querySelector(`[name*="[discount]"]`).value || 0);
            const priceAfterDiscount = Number((price * (1 - discount / 100)).toFixed(4));
            const amount = quantity * priceAfterDiscount;
            const lineTotal = mode === "fop" ? amount * (1 + markup / 100) : amount;
            const unit = line.querySelector('[data-order-line-unit]');
            if (unit) unit.textContent = line.querySelector('[name*="[productId]"]').selectedOptions[0]?.dataset.unit ?? '';
            totalWeight += quantity * Number(line.querySelector('[name*="[productId]"]').selectedOptions[0]?.dataset.weight ?? 0) / 1000;

            line.querySelector("[data-order-line-total]").textContent = `${lineTotal.toFixed(2)} грн`;
            line.querySelector("[data-order-line-vat-price]").value = (priceAfterDiscount * 1.2).toFixed(4);
            line.querySelector("[data-order-line-fop-price]").value = (priceAfterDiscount * (1 + markup / 100)).toFixed(4);
            total += lineTotal;
        });

        const totalWithMarkup = mode === "vat" ? total * 1.2 : total;
        this.markupTarget.value = String(markup);
        this.totalTarget.textContent = `${total.toFixed(2)} грн`;
        this.totalWithMarkupTarget.textContent = `${totalWithMarkup.toFixed(2)} грн`;
        if (this.hasTotalWeightTarget) this.totalWeightTarget.textContent = totalWeight.toFixed(3);
        if (this.hasVatAmountTarget) this.vatAmountTarget.textContent = `${(mode === 'vat' ? total * 0.2 : 0).toFixed(2)} грн`;
        if (this.hasBalanceTarget) this.balanceTarget.textContent = `${totalWithMarkup.toFixed(2)} грн`;
        if (this.hasBudgetTarget) {
            const hasLines = this.linesTarget.querySelectorAll('[data-order-line]').length > 0;
            this.budgetTarget.readOnly = hasLines;
            if (hasLines) this.budgetTarget.value = totalWithMarkup.toFixed(2);
        }
    }

    remove(event) {
        if (this.allowEmptyValue || this.linesTarget.querySelectorAll("[data-order-line]").length > 1) {
            event.currentTarget.closest("[data-order-line]").remove();
            this.recalculate();
        }
    }

    effectiveMarkup() {
        if (this.markupModeTarget.value === "vat") {
            return 20;
        }

        if (this.markupModeTarget.value === "fop") {
            return Number(this.markupFopTarget.value);
        }

        return 0;
    }

    setBasePriceFromSpecialPrice(input, multiplier) {
        const specialPrice = Number(input.value);
        const line = input.closest("[data-order-line]");
        line.querySelector(`[name*="[unitPrice]"]`).value = (specialPrice / multiplier).toFixed(4);
        line.querySelector(`[name*="[discount]"]`).value = "0.00";
        this.recalculate();
    }

    updateMarkupColumns() {
        const mode = this.markupModeTarget.value;
        const isFop = mode === "fop";
        const isVat = mode === "vat";
        if (this.hasTotalsCellTarget) this.totalsCellTarget.colSpan = 6 + (isFop || isVat ? 1 : 0);

        this.markupFopTarget.hidden = !isFop;
        this.markupSuffixTarget.hidden = !isFop && !isVat;
        if (this.hasBudgetTarget) this.markupSuffixTarget.textContent = isVat ? '20 %' : '%';
        this.vatColumnTargets.forEach((column) => { column.hidden = !isVat; });
        this.fopColumnTargets.forEach((column) => { column.hidden = !isFop; });
    }
}
