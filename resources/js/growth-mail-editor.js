import { Editor } from '@tiptap/core';
import Bold from '@tiptap/extension-bold';
import BulletList from '@tiptap/extension-bullet-list';
import Document from '@tiptap/extension-document';
import HardBreak from '@tiptap/extension-hard-break';
import History from '@tiptap/extension-history';
import Italic from '@tiptap/extension-italic';
import Link from '@tiptap/extension-link';
import ListItem from '@tiptap/extension-list-item';
import OrderedList from '@tiptap/extension-ordered-list';
import Paragraph from '@tiptap/extension-paragraph';
import Text from '@tiptap/extension-text';
import Underline from '@tiptap/extension-underline';
import '../css/growth-mail-editor.css';

const mount = document.querySelector('[data-mail-editor]');
const field = document.getElementById('mail_body');

if (mount && field) {
    const locked = mount.dataset.mailLocked === '1';
    const frame = document.querySelector('[data-mail-frame]');
    const tools = document.querySelector('[data-mail-frame]')?.parentElement.querySelector('[data-mail-tools]');
    const finalField = document.querySelector('[data-mail-final-html]');
    const themes = JSON.parse(document.getElementById('mail-template-themes')?.textContent || '{}');
    let dirty = false;
    let mode = 'visual';

    const escapeHtml = (value) => value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const looksLikeHtml = (value) => /<[a-z]/i.test(value);

    const plainToEditorHtml = (text) => {
        const trimmed = text.trim();
        if (trimmed === '') {
            return '<p></p>';
        }

        return trimmed.split(/\n{2,}/).map((block) => {
            const lines = block.split('\n');
            const items = lines.map((line) => line.match(/^(?:[-*•]|\d+[.)])\s+(.+)$/));
            if (items.every((item) => item)) {
                return `<ul>${items.map((item) => `<li>${escapeHtml(item[1])}</li>`).join('')}</ul>`;
            }

            return `<p>${lines.map((line) => escapeHtml(line)).join('<br>')}</p>`;
        }).join('');
    };

    mount.classList.add('growth-mail-editor');

    const editor = new Editor({
        element: mount,
        editable: !locked,
        extensions: [
            Document,
            Paragraph,
            Text,
            Bold,
            Italic,
            Underline,
            BulletList,
            OrderedList,
            ListItem,
            HardBreak,
            History,
            Link.configure({
                openOnClick: false,
                autolink: false,
                protocols: ['http', 'https', 'mailto'],
                validate: (href) => /^(https?:\/\/|mailto:)/i.test(href),
            }),
        ],
        content: looksLikeHtml(field.value) ? field.value : plainToEditorHtml(field.value),
        editorProps: {
            attributes: {
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-labelledby': 'mail_body_label',
            },
        },
        onUpdate: () => {
            dirty = true;
            syncBody();
            if (mode === 'html') {
                refreshFinal();
            }
        },
    });

    const syncBody = () => {
        if (dirty) {
            field.value = editor.getHTML();
        }
    };

    window.growthSyncMailBody = syncBody;

    const selectedTemplate = () => document.querySelector('[data-mail-template-choice]:checked')?.value || 'classic';

    const paint = () => {
        const theme = themes[selectedTemplate()] || themes.classic;
        if (!theme || !frame) {
            return;
        }

        frame.style.background = theme.page;
        frame.dataset.mailTemplate = selectedTemplate();
        const header = frame.querySelector('[data-mail-frame-header]');
        const footer = frame.querySelector('[data-mail-frame-footer]');
        const button = frame.querySelector('[data-mail-frame-button]');
        if (header) {
            header.textContent = theme.header;
            header.style.background = theme.header_bg;
            header.style.color = theme.header_color;
        }
        if (footer) {
            footer.textContent = theme.footer;
        }
        if (button) {
            button.style.background = theme.button;
        }
    };

    const finalHtml = () => {
        const shell = document.getElementById(`mail-shell-${selectedTemplate()}`);
        const preheader = document.getElementById('mail_preheader')?.value.trim() || '';
        const hidden = `<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">${escapeHtml(preheader)}${'&nbsp;&zwnj;'.repeat(40)}</div>`;
        if (!shell) {
            return hidden;
        }

        const parsed = new DOMParser().parseFromString(shell.innerHTML, 'text/html');
        const cell = parsed.querySelector('[data-pne-mail-body]');
        if (cell) {
            cell.innerHTML = editor.getHTML();
            cell.querySelectorAll('script,iframe,object,embed,form,style').forEach((node) => node.remove());
            cell.querySelectorAll('a').forEach((link) => {
                const href = link.getAttribute('href') || '';
                if (href === '[LINK DO ZAPISU]' || /^\s*javascript:/i.test(href)) {
                    link.remove();
                }
            });
        }

        const table = parsed.querySelector('table[data-pne-mail]');

        return hidden + (table ? table.outerHTML : '');
    };

    const refreshFinal = () => {
        if (finalField) {
            finalField.value = finalHtml();
        }
    };

    const setMode = (next) => {
        mode = next;
        const visualOn = next === 'visual';
        frame?.classList.toggle('d-none', !visualOn);
        tools?.classList.toggle('d-none', !visualOn);
        finalField?.classList.toggle('d-none', visualOn);
        document.querySelectorAll('[data-mail-mode]').forEach((button) => {
            const active = button.dataset.mailMode === next;
            button.classList.toggle('btn-primary', active);
            button.classList.toggle('btn-outline-primary', !active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (!visualOn) {
            refreshFinal();
        }
    };

    document.querySelectorAll('[data-mail-mode]').forEach((button) => {
        button.addEventListener('click', () => setMode(button.dataset.mailMode));
    });

    document.querySelectorAll('[data-mail-command]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            if (locked || mode !== 'visual') {
                return;
            }

            const command = button.dataset.mailCommand;
            const chain = editor.chain().focus();
            if (command === 'clear') {
                chain.unsetAllMarks().clearNodes().run();
            } else if (typeof chain[command] === 'function') {
                chain[command]().run();
            }
        });
    });

    document.querySelector('[data-mail-link]')?.addEventListener('mousedown', (event) => event.preventDefault());

    document.getElementById('mail-editor-link-save')?.addEventListener('click', () => {
        const url = document.getElementById('mail-editor-link-url')?.value.trim() || '';
        const error = document.getElementById('mail-editor-link-error');
        if (!/^(https?:\/\/|mailto:|\[LINK DO ZAPISU\])/i.test(url)) {
            error?.classList.remove('d-none');

            return;
        }

        error?.classList.add('d-none');
        if (/^(https?:\/\/|mailto:)/i.test(url)) {
            editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
        }
        document.querySelector('#mail-editor-link [data-bs-dismiss="modal"]')?.click();
    });

    document.querySelector('[data-mail-copy-html]')?.addEventListener('click', async () => {
        const status = document.querySelector('[data-mail-copy-html-status]');
        const html = finalHtml();
        if (finalField) {
            finalField.value = html;
        }
        try {
            await navigator.clipboard.writeText(html);
        } catch {
            setMode('html');
            finalField?.select();
            document.execCommand('copy');
        }
        status?.classList.remove('d-none');
    });

    document.querySelectorAll('[data-mail-template-choice]').forEach((input) => {
        input.addEventListener('change', () => {
            paint();
            if (mode === 'html') {
                refreshFinal();
            }
        });
    });

    document.getElementById('mail_preheader')?.addEventListener('input', () => {
        if (mode === 'html') {
            refreshFinal();
        }
    });

    document.getElementById('material-save')?.addEventListener('submit', syncBody);

    if (locked) {
        document.querySelectorAll('[data-mail-command], [data-mail-link]').forEach((button) => {
            button.disabled = true;
        });
    }

    paint();
    refreshFinal();
}
