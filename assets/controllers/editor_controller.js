import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['holder', 'input'];
    static values = {
        content: { type: String, default: '{}' },
        uploadUrl: String,
    };

    async connect() {
        const EditorJS = (await import('@editorjs/editorjs')).default;
        const Header = (await import('@editorjs/header')).default;
        const List = (await import('@editorjs/list')).default;
        const ImageTool = (await import('@editorjs/image')).default;
        const Table = (await import('@editorjs/table')).default;
        const Quote = (await import('@editorjs/quote')).default;
        const CodeTool = (await import('@editorjs/code')).default;
        const Delimiter = (await import('@editorjs/delimiter')).default;
        const Warning = (await import('@editorjs/warning')).default;
        const Checklist = (await import('@editorjs/checklist')).default;
        const RawTool = (await import('@editorjs/raw')).default;

        let initialData = {};
        try {
            initialData = JSON.parse(this.contentValue);
        } catch (e) {
            initialData = {};
        }

        this.editor = new EditorJS({
            holder: this.holderTarget,
            placeholder: 'Commencez à écrire votre article...',
            data: initialData,
            tools: {
                header: {
                    class: Header,
                    config: {
                        placeholder: 'Titre',
                        levels: [2, 3, 4],
                        defaultLevel: 2,
                    },
                },
                list: {
                    class: List,
                    inlineToolbar: true,
                    config: {
                        defaultStyle: 'unordered',
                    },
                },
                image: {
                    class: ImageTool,
                    config: {
                        endpoints: {
                            byFile: this.uploadUrlValue,
                        },
                        field: 'image',
                    },
                },
                table: {
                    class: Table,
                    inlineToolbar: true,
                    config: {
                        rows: 2,
                        cols: 3,
                        withHeadings: true,
                    },
                },
                quote: {
                    class: Quote,
                    config: {
                        quotePlaceholder: 'Citation...',
                        captionPlaceholder: 'Auteur',
                    },
                },
                code: CodeTool,
                delimiter: Delimiter,
                warning: {
                    class: Warning,
                    config: {
                        titlePlaceholder: 'Titre',
                        messagePlaceholder: 'Message',
                    },
                },
                checklist: {
                    class: Checklist,
                    inlineToolbar: true,
                },
                raw: {
                    class: RawTool,
                    config: {
                        placeholder: 'HTML brut...',
                    },
                },
            },
        });
    }

    async save() {
        if (!this.editor) return;
        const data = await this.editor.save();
        this.inputTarget.value = JSON.stringify(data);
    }

    disconnect() {
        this.editor?.destroy();
    }
}
