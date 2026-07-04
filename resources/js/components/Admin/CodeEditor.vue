<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { EditorState } from '@codemirror/state';
import { EditorView, keymap, lineNumbers, highlightActiveLine, highlightActiveLineGutter, drawSelection } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { indentOnInput, syntaxHighlighting, defaultHighlightStyle, bracketMatching } from '@codemirror/language';
import { autocompletion, closeBrackets } from '@codemirror/autocomplete';
import { searchKeymap } from '@codemirror/search';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { javascript } from '@codemirror/lang-javascript';

const props = withDefaults(defineProps<{
    lang?: 'html' | 'css' | 'js';
    minHeight?: number;
    readonly?: boolean;
}>(), { lang: 'html', minHeight: 500, readonly: false });

const model = defineModel<string>({ default: '' });
const container = ref<HTMLElement | null>(null);
let view: EditorView | null = null;
let ignoreUpdate = false;

const langExtension = () => {
    if (props.lang === 'css') return css();
    if (props.lang === 'js') return javascript();
    return html();
};

const darkTheme = EditorView.theme({
    '&': { background: '#0a0f1a', color: '#e2e8f0', height: '100%' },
    '.cm-scroller': {
        fontFamily: '"JetBrains Mono", "Fira Code", "Cascadia Code", monospace',
        fontSize: '13px',
        lineHeight: '1.65',
    },
    '.cm-content': { padding: '12px 0', caretColor: '#3b82f6' },
    '.cm-focused': { outline: 'none' },
    '.cm-gutters': { background: '#0d0d0f', borderRight: '1px solid #27272a', color: '#3f3f46', minWidth: '2.8em' },
    '.cm-activeLineGutter': { background: '#111113', color: '#52525b' },
    '.cm-activeLine': { background: '#111113' },
    '.cm-selectionBackground': { background: '#3b82f622 !important' },
    '.cm-cursor': { borderLeftColor: '#3b82f6' },
    '.cm-matchingBracket': { background: '#3b82f620', outline: '1px solid #3b82f650' },
    // Syntax colors
    '.tok-keyword': { color: '#60a5fa' },
    '.tok-comment': { color: '#52525b', fontStyle: 'italic' },
    '.tok-string': { color: '#86efac' },
    '.tok-number': { color: '#fbbf24' },
    '.tok-tagName': { color: '#f472b6' },
    '.tok-attributeName': { color: '#93c5fd' },
    '.tok-attributeValue': { color: '#86efac' },
    '.tok-operator': { color: '#94a3b8' },
    '.tok-punctuation': { color: '#64748b' },
    '.tok-variableName': { color: '#e2e8f0' },
    '.tok-typeName': { color: '#a5b4fc' },
    '.tok-propertyName': { color: '#93c5fd' },
    '.tok-angleBracket': { color: '#64748b' },
}, { dark: true });

onMounted(() => {
    const extensions = [
        lineNumbers(),
        highlightActiveLine(),
        highlightActiveLineGutter(),
        history(),
        drawSelection(),
        indentOnInput(),
        syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
        bracketMatching(),
        closeBrackets(),
        autocompletion(),
        keymap.of([...defaultKeymap, ...historyKeymap, ...searchKeymap]),
        langExtension(),
        darkTheme,
        EditorView.updateListener.of((update) => {
            if (update.docChanged && !ignoreUpdate) {
                model.value = update.state.doc.toString();
            }
        }),
        EditorView.editable.of(!props.readonly),
        EditorState.readOnly.of(props.readonly),
    ];

    view = new EditorView({
        state: EditorState.create({ doc: model.value, extensions }),
        parent: container.value!,
    });
});

watch(() => model.value, (val) => {
    if (view && val !== view.state.doc.toString()) {
        ignoreUpdate = true;
        view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: val } });
        ignoreUpdate = false;
    }
});

onBeforeUnmount(() => view?.destroy());
</script>

<template>
    <div
        ref="container"
        :style="`min-height: ${minHeight}px;`"
        class="overflow-auto"
    />
</template>
