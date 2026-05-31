import { marked } from 'marked';

marked.use({
    gfm: true,
    breaks: true,
});

export function renderMarkdown(src) {
    if (!src) return '';
    return marked.parse(String(src));
}
