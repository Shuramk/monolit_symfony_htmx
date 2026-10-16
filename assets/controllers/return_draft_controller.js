import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["rows", "template"];

    connect() {
        this.index = this.rowsTarget.querySelectorAll("tr").length;
        if (this.index === 0) this.add();
    }

    add() {
        const row = this.templateTarget.content.cloneNode(true);
        row.querySelectorAll("[name]").forEach((field) => {
            field.name = field.name.replace("INDEX", String(this.index));
        });
        this.index += 1;
        this.rowsTarget.appendChild(row);
    }

    remove(event) {
        event.currentTarget.closest("tr").remove();
    }
}
