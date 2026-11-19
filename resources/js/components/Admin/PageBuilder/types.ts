export interface BlockField {
    key: string;
    type: 'text' | 'textarea' | 'markdown' | 'url' | 'image' | 'number' | 'select' | 'toggle' | 'game' | 'list';
    label: string;
    max?: number;
    min?: number;
    options?: Record<string, string>;
    fields?: BlockField[];
    max_items?: number;
    default?: unknown;
}

export interface BlockType {
    type: string;
    label: string;
    description: string;
    icon: string;
    category: string;
    fields: BlockField[];
    defaults: Record<string, any>;
}

export interface PageBlock {
    id: string;
    type: string;
    data: Record<string, any>;
}

export interface GameOption {
    id: number;
    name: string;
}

export const newBlockId = () => Math.random().toString(36).slice(2, 14);

/** Empty values for a list item, built from its fields. */
export function emptyItem(fields: BlockField[]): Record<string, any> {
    return Object.fromEntries(fields.map((f) => [f.key, f.default ?? (f.type === 'list' ? [] : f.type === 'toggle' ? false : f.type === 'game' ? null : '')]));
}
