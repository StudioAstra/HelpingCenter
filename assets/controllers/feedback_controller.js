import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['buttons', 'commentForm', 'thankYou', 'comment'];
    static values = { url: String };

    async vote(event) {
        const isHelpful = event.currentTarget.dataset.helpful === 'true';

        if (!isHelpful) {
            this.buttonsTarget.classList.add('hidden');
            this.commentFormTarget.classList.remove('hidden');
            this._isHelpful = false;
            return;
        }

        await this.submit(true, null);
    }

    async submitComment(event) {
        event.preventDefault();
        const comment = this.commentTarget.value.trim();
        await this.submit(false, comment || null);
    }

    async submit(isHelpful, comment) {
        try {
            await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ is_helpful: isHelpful, comment }),
            });

            this.buttonsTarget.classList.add('hidden');
            this.commentFormTarget.classList.add('hidden');
            this.thankYouTarget.classList.remove('hidden');
        } catch (e) {
            // Silently fail
        }
    }
}
