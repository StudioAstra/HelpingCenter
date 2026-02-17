import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['holder', 'input'];
    static values = {
        content: { type: String, default: '{}' },
    };

    async connect() {
        const EasyMDE = (await import('easymde')).default;

        const initialRawContent =
            this.contentValue && this.contentValue !== '{}'
                ? this.contentValue
                : this.inputTarget.value;
        const content = this.parseContent(initialRawContent);
        const initialMarkdown = this.contentToMarkdown(content);

        const textarea = document.createElement('textarea');
        this.holderTarget.innerHTML = '';
        this.holderTarget.appendChild(textarea);

        this.editor = new EasyMDE({
            element: textarea,
            initialValue: initialMarkdown,
            placeholder: 'Commencez à écrire votre article...',
            spellChecker: false,
            status: false,
            forceSync: true,
            toolbar: [
                'bold',
                'italic',
                'heading',
                '|',
                'quote',
                'unordered-list',
                'ordered-list',
                '|',
                'link',
                {
                    name: 'image',
                    title: 'Televerser une image',
                    className: 'fa fa-image',
                    action: () => this.openImagePicker(),
                },
                '|',
                'preview',
                'side-by-side',
                'fullscreen',
                '|',
                'guide',
            ],
        });

        this.inputTarget.value = JSON.stringify({ markdown: initialMarkdown });
        this.editor.codemirror.on('change', () => {
            this.inputTarget.value = JSON.stringify({ markdown: this.editor.value() });
        });
    }

    save() {
        if (!this.editor) return;
        this.inputTarget.value = JSON.stringify({ markdown: this.editor.value() });
    }

    disconnect() {
        if (this.editor) {
            this.editor.toTextArea();
            this.editor = null;
        }
    }

    parseContent(value) {
        try {
            return JSON.parse(value);
        } catch (e) {
            return {};
        }
    }

    contentToMarkdown(content) {
        if (typeof content?.markdown === 'string') {
            return content.markdown;
        }

        if (!Array.isArray(content?.blocks)) {
            return '';
        }

        const lines = content.blocks
            .map((block) => this.blockToMarkdown(block))
            .filter((line) => line.length > 0);

        return lines.join('\n\n').trim();
    }

    blockToMarkdown(block) {
        const data = block?.data ?? {};

        switch (block?.type) {
            case 'header': {
                const level = Math.min(Math.max(Number(data.level) || 2, 1), 6);
                const text = this.stripHtml(data.text ?? '');
                return `${'#'.repeat(level)} ${text}`.trim();
            }
            case 'paragraph':
                return this.stripHtml(data.text ?? '');
            case 'list': {
                const items = Array.isArray(data.items) ? data.items : [];
                const ordered = data.style === 'ordered';

                return items
                    .map((item, index) => {
                        const raw = typeof item === 'object' ? (item.content ?? '') : item;
                        const text = this.stripHtml(String(raw));
                        return ordered ? `${index + 1}. ${text}` : `- ${text}`;
                    })
                    .join('\n');
            }
            case 'checklist': {
                const items = Array.isArray(data.items) ? data.items : [];
                return items
                    .map((item) => {
                        const checked = item?.checked ? 'x' : ' ';
                        const text = this.stripHtml(item?.text ?? '');
                        return `- [${checked}] ${text}`;
                    })
                    .join('\n');
            }
            case 'quote': {
                const text = this.stripHtml(data.text ?? '');
                const caption = this.stripHtml(data.caption ?? '');
                return caption ? `> ${text}\n>\n> ${caption}` : `> ${text}`;
            }
            case 'code':
                return `\`\`\`\n${data.code ?? ''}\n\`\`\``;
            case 'delimiter':
                return '---';
            case 'image': {
                const url = data?.file?.url ?? data?.url ?? '';
                if (!url) return '';
                const caption = this.stripHtml(data.caption ?? '');
                return `![${caption}](${url})`;
            }
            case 'table': {
                const rows = Array.isArray(data.content) ? data.content : [];
                if (rows.length === 0) return '';

                const normalizedRows = rows.map((row) =>
                    Array.isArray(row) ? row.map((cell) => this.stripHtml(String(cell ?? ''))) : []
                );
                const maxCols = Math.max(...normalizedRows.map((row) => row.length), 1);
                const paddedRows = normalizedRows.map((row) => {
                    const next = [...row];
                    while (next.length < maxCols) next.push('');
                    return next;
                });

                const headerRow = paddedRows[0];
                const separatorRow = Array(maxCols).fill('---');
                const bodyRows = paddedRows.slice(1);
                const tableRows = [headerRow, separatorRow, ...bodyRows];

                return tableRows.map((row) => `| ${row.join(' | ')} |`).join('\n');
            }
            case 'warning':
            case 'alert': {
                const title = this.stripHtml(data.title ?? '');
                const message = this.stripHtml(data.message ?? data.text ?? '');
                if (title && message) return `> **${title}**\n>\n> ${message}`;
                if (title) return `> **${title}**`;
                if (message) return `> ${message}`;
                return '';
            }
            case 'raw':
                return data.html ?? '';
            default:
                return '';
        }
    }

    stripHtml(value) {
        const container = document.createElement('div');
        container.innerHTML = value;
        return (container.textContent || container.innerText || '').trim();
    }

    openImagePicker() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';

        input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                window.alert('Le fichier choisi doit etre une image.');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                window.alert('Image trop volumineuse (max 5 Mo).');
                return;
            }

            const { url, message } = await this.uploadImage(file);
            if (!url) {
                window.alert(message || "Impossible d'envoyer l'image.");
                return;
            }

            const markdown = `![${file.name}](${url})`;
            this.editor.codemirror.replaceSelection(markdown);
            this.editor.codemirror.focus();
            this.inputTarget.value = JSON.stringify({ markdown: this.editor.value() });
        });

        input.click();
    }

    async uploadImage(file) {
        const formData = new FormData();
        formData.append('image', file);

        try {
            const response = await fetch('/admin/upload/image', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (response.status === 413) {
                    return { url: null, message: 'Le serveur refuse ce fichier (HTTP 413). Reduis la taille de l image.' };
                }

                const serverMessage = payload?.message || '';
                const fallback = `Erreur HTTP ${response.status} pendant le televersement.`;
                return { url: null, message: serverMessage || fallback };
            }

            if (payload?.success !== 1 || !payload?.file?.url) {
                return { url: null, message: payload?.message || 'Reponse invalide du serveur.' };
            }

            return { url: payload.file.url, message: null };
        } catch (e) {
            return { url: null, message: 'Erreur reseau pendant le televersement.' };
        }
    }
}
