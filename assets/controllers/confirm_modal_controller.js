import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        title: { type: String, default: 'Confirmer l action' },
        message: { type: String, default: 'Confirmer cette action ?' },
        confirmLabel: { type: String, default: 'Confirmer' },
        cancelLabel: { type: String, default: 'Annuler' },
        variant: { type: String, default: 'danger' },
    };

    connect() {
        this.submittingConfirmed = false;
        this.ensureModal();
    }

    confirm(event) {
        if (this.submittingConfirmed) {
            this.submittingConfirmed = false;
            return;
        }

        event.preventDefault();
        confirmModalState.activeController = this;
        this.renderModal();
        confirmModalState.backdrop.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    ensureModal() {
        if (confirmModalState.backdrop) {
            return;
        }

        const backdrop = document.createElement('div');
        backdrop.className = 'hidden fixed inset-0 z-[80]';
        backdrop.innerHTML = `
            <div class="absolute inset-0 bg-black/40"></div>
            <div class="relative mx-auto mt-24 w-[92%] max-w-md rounded-2xl border border-[rgba(0,0,0,0.06)] bg-white p-6 shadow-xl">
                <h3 data-confirm-modal-role="title" class="text-lg font-semibold text-[#2d3748]"></h3>
                <p data-confirm-modal-role="message" class="mt-2 text-sm text-[#2d3748]/70"></p>
                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" data-confirm-modal-role="cancel" class="inline-flex items-center rounded-xl border border-[rgba(0,0,0,0.06)] px-4 py-2 text-sm font-medium text-[#2d3748]/70 hover:bg-[#f0f4f8] transition-colors"></button>
                    <button type="button" data-confirm-modal-role="confirm" class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white transition-colors"></button>
                </div>
            </div>
        `;

        document.body.appendChild(backdrop);

        const title = backdrop.querySelector('[data-confirm-modal-role="title"]');
        const message = backdrop.querySelector('[data-confirm-modal-role="message"]');
        const cancel = backdrop.querySelector('[data-confirm-modal-role="cancel"]');
        const confirm = backdrop.querySelector('[data-confirm-modal-role="confirm"]');
        const overlay = backdrop.firstElementChild;

        cancel.addEventListener('click', () => closeModal());
        overlay.addEventListener('click', () => closeModal());
        confirm.addEventListener('click', () => {
            const controller = confirmModalState.activeController;
            closeModal();
            if (controller) {
                controller.submittingConfirmed = true;
                controller.element.requestSubmit();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !confirmModalState.backdrop.classList.contains('hidden')) {
                closeModal();
            }
        });

        confirmModalState.backdrop = backdrop;
        confirmModalState.title = title;
        confirmModalState.message = message;
        confirmModalState.cancel = cancel;
        confirmModalState.confirm = confirm;
    }

    renderModal() {
        confirmModalState.title.textContent = this.titleValue;
        confirmModalState.message.textContent = this.messageValue;
        confirmModalState.cancel.textContent = this.cancelLabelValue;
        confirmModalState.confirm.textContent = this.confirmLabelValue;

        if (this.variantValue === 'primary') {
            confirmModalState.confirm.className = 'inline-flex items-center rounded-xl bg-[#635bff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#635bff]/90 transition-colors';
            return;
        }

        confirmModalState.confirm.className = 'inline-flex items-center rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 transition-colors';
    }
}

const confirmModalState = {
    activeController: null,
    backdrop: null,
    title: null,
    message: null,
    cancel: null,
    confirm: null,
};

function closeModal() {
    if (!confirmModalState.backdrop) {
        return;
    }

    confirmModalState.backdrop.classList.add('hidden');
    confirmModalState.activeController = null;
    document.body.classList.remove('overflow-hidden');
}

