import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['link'];

    connect() {
        this.observer = new IntersectionObserver(
            (entries) => this.onIntersect(entries),
            { rootMargin: '-80px 0px -70% 0px', threshold: 0 }
        );

        document.querySelectorAll('.article-content h2, .article-content h3, .article-content h4').forEach(heading => {
            if (heading.id) {
                this.observer.observe(heading);
            }
        });
    }

    disconnect() {
        this.observer?.disconnect();
    }

    onIntersect(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                this.setActive(entry.target.id);
            }
        });
    }

    setActive(id) {
        this.linkTargets.forEach(link => {
            if (link.getAttribute('href') === `#${id}`) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }

    scrollTo(event) {
        event.preventDefault();
        const href = event.currentTarget.getAttribute('href');
        const target = document.querySelector(href);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    }
}
