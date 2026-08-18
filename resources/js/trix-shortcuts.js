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
        if (applyQuoteShortcut(editor, context)) {
            return;
        }

        applyBoldShortcut(editor, context);
    } finally {
        processingEditors.delete(editorElement);
    }
});
