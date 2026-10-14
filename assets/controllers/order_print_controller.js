import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['stamp'];

    print() {
        window.print();
    }

    togglePaid(event) {
        this.stampTarget.hidden = !event.target.checked;
    }
}
