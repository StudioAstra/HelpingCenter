import { Controller } from '@hotwired/stimulus';

const RECENT_KEY = 'help_recent_articles_v1';

export default class extends Controller {
    static targets = ['list', 'empty'];

    connect() {
        const items = this.readRecent();
        if (items.length === 0) {
            if (this.hasEmptyTarget) this.emptyTarget.classList.remove('hidden');
            return;
        }

        if (this.hasEmptyTarget) this.emptyTarget.classList.add('hidden');
        if (this.hasListTarget) {
            this.listTarget.innerHTML = items
                .slice(0, 6)
                .map(
                    (item) => `
                        <a href="${item.url}" class="group block rounded-xl border border-[rgba(0,0,0,0.10)] bg-white p-4 hover:border-[#635bff]/30 transition">
                            <div class="text-xs font-medium text-[#2d3748]/65">${item.section || 'Article'}</div>
                            <div class="mt-1 text-sm font-semibold text-[#1f2937] group-hover:text-[#635bff]">${item.title}</div>
                        </a>
                    `
                )
                .join('');
        }
    }

    readRecent() {
        try {
            const raw = localStorage.getItem(RECENT_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }
}

