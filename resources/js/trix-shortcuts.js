const processingEditors = new WeakSet();

function getCursorContext(editor) {
    const range = editor?.getSelectedRange?.();

    if (!Array.isArray(range)) {
        return null;
    }

    const [start, end = start] = range;

    if (start !== end) {
        return null;
    }

    const beforeCursor = editor.getDocument().toString().slice(0, start);
    const lineStart = beforeCursor.lastIndexOf('\n') + 1;

    return {
        cursor: start,
        lineBeforeCursor: beforeCursor.slice(lineStart),
    };
}

function deleteCharactersBackward(editor, count) {
    for (let index = 0; index < count; index += 1) {
        editor.deleteInDirection('backward');
    }
}

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

// Converte Markdown inline depois de escapar o texto. Os placeholders impedem
// que negrito/link sejam interpretados dentro de um trecho de código.
function convertInlineMarkdown(rawText) {
    const codeSpans = [];
    const escaped = escapeHtml(rawText).replace(/`([^`\n]+)`/g, (_match, code) => {
        const placeholder = `\u0000CODE${codeSpans.length}\u0000`;
        codeSpans.push(`<code>${code}</code>`);
        return placeholder;
    });

    let converted = escaped
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2">$1</a>');

    return converted.replace(/\u0000CODE(\d+)\u0000/g, (_match, index) => codeSpans[index]);
}

const convertInlineBold = convertInlineMarkdown;

function applyQuoteShortcut(editor, context) {
    if (context.lineBeforeCursor !== '> ') {
        return false;
    }

    editor.recordUndoEntry('Markdown quote');
    deleteCharactersBackward(editor, 2);
    editor.activateAttribute('quote');

    return true;
}

function applyBoldShortcut(editor, context) {
    if (!context.lineBeforeCursor.endsWith('**')) {
        return false;
    }

    const closingStart = context.lineBeforeCursor.length - 2;
    const openingStart = context.lineBeforeCursor.slice(0, closingStart).lastIndexOf('**');

    if (openingStart === -1) {
        return false;
    }

    const rawContent = context.lineBeforeCursor.slice(openingStart + 2, closingStart);
    const content = rawContent.trim();

    if (!content) {
        return false;
    }

    editor.recordUndoEntry('Markdown bold');
    deleteCharactersBackward(editor, context.lineBeforeCursor.length - openingStart);
    editor.insertHTML(`<strong>${escapeHtml(content)}</strong>`);
    editor.deactivateAttribute('bold');

    return true;
}

function applyInlineCodeShortcut(editor, context) {
    if (!context.lineBeforeCursor.endsWith('`') || context.lineBeforeCursor.endsWith('``')) {
        return false;
    }

    const closingStart = context.lineBeforeCursor.length - 1;
    const openingStart = context.lineBeforeCursor.slice(0, closingStart).lastIndexOf('`');

    if (openingStart === -1) {
        return false;
    }

    const content = context.lineBeforeCursor.slice(openingStart + 1, closingStart);

    if (!content) {
        return false;
    }

    editor.recordUndoEntry('Markdown inline code');
    deleteCharactersBackward(editor, context.lineBeforeCursor.length - openingStart);
    editor.insertHTML(`<code>${escapeHtml(content)}</code>`);

    return true;
}

// `# ` vira título de verdade (heading1 nativo do Trix) — entra no sumário.
function applyHeadingShortcut(editor, context) {
    if (context.lineBeforeCursor !== '# ') {
        return false;
    }

    editor.recordUndoEntry('Markdown heading');
    deleteCharactersBackward(editor, 2);
    editor.activateAttribute('heading1');

    return true;
}

// `## ` vira só negrito (subtítulo visual) — não é heading, não entra no sumário.
function applySubtitleShortcut(editor, context) {
    if (context.lineBeforeCursor !== '## ') {
        return false;
    }

    editor.recordUndoEntry('Markdown subtitle');
    deleteCharactersBackward(editor, 3);
    editor.activateAttribute('bold');

    return true;
}

// Linha de rule (`<hr>`) precisa entrar como "content attachment" — é a forma
// documentada do Trix pra elementos não-textuais fora do seu modelo de blocos.
function insertHorizontalRule(editor) {
    editor.insertAttachment(new Trix.Attachment({
        content: '<hr>',
        contentType: 'application/vnd.trix.horizontal-rule',
    }));
}

// `---` (3+ traços, linha inteira) vira linha horizontal.
function applyHorizontalRuleShortcut(editor, context) {
    if (!/^-{3,}$/.test(context.lineBeforeCursor)) {
        return false;
    }

    editor.recordUndoEntry('Markdown horizontal rule');
    deleteCharactersBackward(editor, context.lineBeforeCursor.length);
    insertHorizontalRule(editor);

    return true;
}

document.addEventListener('trix-change', (event) => {
    const editorElement = event.target;

    if (editorElement.tagName !== 'TRIX-EDITOR' || processingEditors.has(editorElement)) {
        return;
    }

    const editor = editorElement.editor;
    const context = getCursorContext(editor);

    if (!context) {
        return;
    }

    processingEditors.add(editorElement);

    try {
        if (applyHeadingShortcut(editor, context)) {
            return;
        }

        if (applySubtitleShortcut(editor, context)) {
            return;
        }

        if (applyQuoteShortcut(editor, context)) {
            return;
        }

        if (applyHorizontalRuleShortcut(editor, context)) {
            return;
        }

        if (applyInlineCodeShortcut(editor, context)) {
            return;
        }

        applyBoldShortcut(editor, context);
    } finally {
        processingEditors.delete(editorElement);
    }
});

// --- Colar markdown pronto -------------------------------------------------
// Quando o conteúdo colado é texto puro, convertemos Markdown linha a linha e
// montamos UMA string HTML com todos os blocos,
// inserida de uma vez só via editor.insertHTML — inserir bloco a bloco em
// chamadas separadas vaza atributos (heading/bold) pro bloco seguinte.
// O formato do `<figure data-trix-attachment>` é o mesmo que o Trix gera
// sozinho pra content attachments — confirmado por round-trip via loadHTML.
const HORIZONTAL_RULE_HTML = '<figure data-trix-attachment=\'{"content":"<hr>","contentType":"application/vnd.trix.horizontal-rule"}\' data-trix-content-type="application/vnd.trix.horizontal-rule"><hr></figure>';

function markdownTextToTrixHtml(text) {
    const lines = text.replace(/\r\n/g, '\n').split('\n');
    const blocks = [];
    let quoteBuffer = [];
    let listBuffer = [];
    let listType = null;
    let codeBuffer = null;
    let codeLanguage = '';

    function flushQuote() {
        if (quoteBuffer.length === 0) {
            return;
        }

        const items = quoteBuffer.map((line) => `<div>${convertInlineBold(line)}</div>`).join('');
        blocks.push(`<blockquote>${items}</blockquote>`);
        quoteBuffer = [];
    }

    function flushList() {
        if (listBuffer.length === 0) {
            return;
        }

        blocks.push(`<${listType}>${listBuffer.map((item) => `<li>${convertInlineMarkdown(item)}</li>`).join('')}</${listType}>`);
        listBuffer = [];
        listType = null;
    }

    function flushParagraphBoundaries() {
        flushQuote();
        flushList();
    }

    for (const rawLine of lines) {
        const line = rawLine.trimEnd();

        if (codeBuffer !== null) {
            if (/^\s*```\s*$/.test(line)) {
                const languageClass = codeLanguage ? ` class="language-${escapeHtml(codeLanguage)}"` : '';
                blocks.push(`<pre><code${languageClass}>${escapeHtml(codeBuffer.join('\n'))}</code></pre>`);
                codeBuffer = null;
                codeLanguage = '';
            } else {
                codeBuffer.push(rawLine);
            }
            continue;
        }

        const fenceMatch = line.match(/^\s*```\s*([\w+-]*)\s*$/);

        if (fenceMatch) {
            flushParagraphBoundaries();
            codeBuffer = [];
            codeLanguage = fenceMatch[1];
            continue;
        }

        if (line === '') {
            flushParagraphBoundaries();
            continue;
        }

        if (/^-{3,}$/.test(line)) {
            flushParagraphBoundaries();
            blocks.push(HORIZONTAL_RULE_HTML);
            continue;
        }

        const headingMatch = line.match(/^#\s+(.+)$/);
        if (headingMatch) {
            flushParagraphBoundaries();
            blocks.push(`<h1>${escapeHtml(headingMatch[1].trim())}</h1>`);
            continue;
        }

        const subtitleMatch = line.match(/^##\s+(.+)$/);
        if (subtitleMatch) {
            flushParagraphBoundaries();
            blocks.push(`<div><strong>${convertInlineMarkdown(subtitleMatch[1].trim())}</strong></div>`);
            continue;
        }

        const quoteMatch = line.match(/^>\s?(.*)$/);
        if (quoteMatch) {
            flushList();
            quoteBuffer.push(quoteMatch[1]);
            continue;
        }

        const unorderedMatch = line.match(/^\s*[-*+]\s+(.+)$/);
        const orderedMatch = line.match(/^\s*\d+[.)]\s+(.+)$/);
        const nextListType = unorderedMatch ? 'ul' : orderedMatch ? 'ol' : null;

        if (nextListType) {
            flushQuote();
            if (listType && listType !== nextListType) {
                flushList();
            }
            listType = nextListType;
            listBuffer.push((unorderedMatch || orderedMatch)[1]);
            continue;
        }

        flushParagraphBoundaries();
        blocks.push(`<div>${convertInlineMarkdown(line.trim())}</div>`);
    }

    if (codeBuffer !== null) {
        const languageClass = codeLanguage ? ` class="language-${escapeHtml(codeLanguage)}"` : '';
        blocks.push(`<pre><code${languageClass}>${escapeHtml(codeBuffer.join('\n'))}</code></pre>`);
    }

    flushQuote();
    flushList();

    return blocks.join('');
}

// Não dá pra usar os eventos `trix-paste`/`trix-before-paste`: o código do
// Trix chama `insertString()` incondicionalmente, sem checar `defaultPrevented`
// — ou seja, nenhum dos dois realmente bloqueia a inserção padrão dele.
// Por isso interceptamos o evento NATIVO `paste` do navegador, na fase de
// captura (antes do listener interno do Trix, que está na fase de bubble do
// próprio elemento), e paramos a propagação pra ele nunca rodar.
document.addEventListener('paste', (event) => {
    const editorElement = event.target.closest ? event.target.closest('trix-editor') : null;

    if (!editorElement || !editorElement.editor) {
        return;
    }

    const clipboardData = event.clipboardData;

    if (!clipboardData || (clipboardData.files && clipboardData.files.length > 0)) {
        return;
    }

    const text = clipboardData.getData('text/plain');

    if (!text || !text.trim()) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    const editor = editorElement.editor;
    editor.recordUndoEntry('Markdown paste');
    editor.insertHTML(markdownTextToTrixHtml(text));
}, true);
