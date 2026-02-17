import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'results', 'overlay'];

    connect() {
        this.timeout = null;
    }

    onInput() {
        clearTimeout(this.timeout);
        const query = this.inputTarget.value.trim();

        if (query.length < 2) {
            this.hideResults();
            return;
        }

        this.timeout = setTimeout(() => this.search(query), 300);
    }

    async search(query) {
        try {
            const response = await fetch(`/api/search?q=${encodeURIComponent(query)}`);
            const results = await response.json();
            this.showResults(results);
        } catch (e) {
            this.hideResults();
        }
    }

    showResults(results) {
        if (results.length === 0) {
            this.resultsTarget.innerHTML = `
                <div class="p-4 text-center text-muted-foreground text-sm">
                    Aucun résultat trouvé.
                </div>`;
        } else {
            this.resultsTarget.innerHTML = results.map(r => `
                <a href="${r.url}" class="block px-4 py-3 hover:bg-muted transition-colors border-b border-border last:border-0">
                    <div class="font-medium text-foreground text-sm">${r.title}</div>
                    <div class="text-xs text-muted-foreground mt-0.5">${r.section} &rsaquo; ${r.subsection}</div>
                </a>
            `).join('');
        }

        this.resultsTarget.classList.remove('hidden');
        if (this.hasOverlayTarget) {
            this.overlayTarget.classList.remove('hidden');
        }
    }

    hideResults() {
        this.resultsTarget.classList.add('hidden');
        if (this.hasOverlayTarget) {
            this.overlayTarget.classList.add('hidden');
        }
    }

    closeResults() {
        this.hideResults();
    }

    onFocus() {
        const query = this.inputTarget.value.trim();
        if (query.length >= 2) {
            this.search(query);
        }
    }
}
