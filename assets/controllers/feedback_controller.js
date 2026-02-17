import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['question', 'negativeForm', 'thankYou', 'comment', 'reason'];
    static values = { url: String };

    async vote(event) {
        const isHelpful = event.currentTarget.dataset.helpful === 'true';

        if (!isHelpful) {
            this.questionTarget.classList.add('hidden');
            this.negativeFormTarget.classList.remove('hidden');
            return;
        }

        await this.submit(true, null);
    }

    async submitNegative(event) {
        event.preventDefault();
        const reason = this.hasReasonTarget ? this.reasonTarget.value.trim() : '';
        const comment = this.commentTarget.value.trim();
        let payloadComment = comment;
        if (reason) {
            payloadComment = comment ? `[${reason}] ${comment}` : `[${reason}]`;
        }
        await this.submit(false, payloadComment || null);
    }

    cancel() {
        this.negativeFormTarget.classList.add('hidden');
        this.questionTarget.classList.remove('hidden');
        this.commentTarget.value = '';
        if (this.hasReasonTarget) {
            this.reasonTarget.value = '';
        }
    }

    async submit(isHelpful, comment) {
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ is_helpful: isHelpful, comment }),
            });

            if (!response.ok) {
                return;
            }

            this.questionTarget.classList.add('hidden');
            this.negativeFormTarget.classList.add('hidden');
            this.thankYouTarget.classList.remove('hidden');
            this.commentTarget.value = '';
        } catch (e) {
            // Silently fail
        }
    }
}
