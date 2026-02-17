import { Controller } from '@hotwired/stimulus';

const RECENT_KEY = 'help_recent_articles_v1';

export default class extends Controller {
    static targets = ['copyStatus', 'backToTop', 'resolvedThanks'];
    static values = {
        id: Number,
        title: String,
        section: String,
        url: String,
    };

    connect() {
        this.saveRecentArticle();
        this.onScroll = this.handleScroll.bind(this);
        window.addEventListener('scroll', this.onScroll, { passive: true });
        this.handleScroll();
    }

    disconnect() {
        window.removeEventListener('scroll', this.onScroll);
    }

    async copyLink() {
        try {
            await navigator.clipboard.writeText(this.urlValue || window.location.href);
            this.showCopyStatus('Lien copié');
        } catch (e) {
            this.showCopyStatus('Copie impossible');
        }
    }

    async shareLink() {
        const shareData = {
            title: this.titleValue,
            url: this.urlValue || window.location.href,
        };

        if (navigator.share) {
            try {
                await navigator.share(shareData);
                return;
            } catch (e) {
                // fallback copy
            }
        }

        await this.copyLink();
    }

    scrollTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    markResolved() {
        if (this.hasResolvedThanksTarget) {
            this.resolvedThanksTarget.classList.remove('hidden');
        }
    }

    markUnresolved() {
        window.location.hash = '#feedback';
        const feedback = document.getElementById('feedback');
        if (feedback) {
            feedback.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    saveRecentArticle() {
        const current = {
            id: this.idValue,
            title: this.titleValue,
            section: this.sectionValue,
            url: this.urlValue || window.location.href,
            seenAt: Date.now(),
        };

        const list = this.readRecent();
        const filtered = list.filter((item) => item.id !== current.id);
        filtered.unshift(current);
        const next = filtered.slice(0, 8);
        localStorage.setItem(RECENT_KEY, JSON.stringify(next));
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

    handleScroll() {
        if (!this.hasBackToTopTarget) {
            return;
        }
        const show = window.scrollY > 420;
        this.backToTopTarget.classList.toggle('hidden', !show);
    }

    showCopyStatus(message) {
        if (!this.hasCopyStatusTarget) {
            return;
        }
        this.copyStatusTarget.textContent = message;
        this.copyStatusTarget.classList.remove('hidden');
        window.clearTimeout(this.copyStatusTimeout);
        this.copyStatusTimeout = window.setTimeout(() => {
            this.copyStatusTarget.classList.add('hidden');
        }, 1800);
    }
}

