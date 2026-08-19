import { Controller } from "@hotwired/stimulus";
import { postJson, serializeForm, validationMessage } from "../lib/json_form.js";

export default class extends Controller {
    static targets = ["error"];

    invalid(event) {
        const field = event.target;
        field.setCustomValidity("");
        field.setCustomValidity(validationMessage(field.validity, field));
    }

    clearValidity(event) {
        if (typeof event.target.setCustomValidity === "function") {
            event.target.setCustomValidity("");
        }
    }

    async submit(event) {
        event.preventDefault();
        if (this.submitting || !this.element.reportValidity()) return;

        this.submitting = true;
        this.errorTarget.hidden = true;
        this.errorTarget.textContent = "";
        const buttons = Array.from(this.element.querySelectorAll('button[type="submit"]'));
        const formData = new FormData(this.element);
        buttons.forEach((button) => { button.disabled = true; });
        this.element.setAttribute("aria-busy", "true");

        try {
            const data = await postJson(this.element.action, serializeForm(formData), formData.get("_token"));
            const destination = new URL(data.location, window.location.href);
            if (destination.origin !== window.location.origin) throw new Error("Некорректный адрес перехода.");
            window.location.assign(destination.href);
        } catch (error) {
            this.errorTarget.textContent = error instanceof TypeError
                ? "Не удалось связаться с сервером. Проверьте соединение и повторите отправку."
                : error.message;
            this.errorTarget.hidden = false;
            this.errorTarget.focus();
            this.submitting = false;
            buttons.forEach((button) => { button.disabled = false; });
            this.element.removeAttribute("aria-busy");
        }
    }
}
