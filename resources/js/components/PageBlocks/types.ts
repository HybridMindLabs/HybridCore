/** A block as the server renders it for the public page. */
export interface RenderedBlock {
    id: string;
    type: string;
    component: string;
    data: Record<string, any>;
    props: Record<string, any>;
}

export interface BlockButton {
    label: string;
    url: string;
    style: 'primary' | 'secondary';
}
