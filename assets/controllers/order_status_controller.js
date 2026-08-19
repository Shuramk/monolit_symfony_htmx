import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['reason', 'comment'];

    connect() {
        this.toggle();
    }

    toggle() {
        const cancelled = this.element.querySelector('[name="status"]:checked')?.value === 'cancelled';
        this.reasonTarget.hidden = !cancelled;
        this.commentTarget.required = cancelled;
        this.commentTarget.disabled = !cancelled;
    }
}
