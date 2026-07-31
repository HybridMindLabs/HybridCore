import { existsSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const MARKER = '/* @hybridcore:extension-sources */';

/**
 * Registers every installed extension's Vue/TS tree as a Tailwind source.
 *
 * Extensions live under a gitignored path. Tailwind skips gitignored files in
 * automatic detection and also filters any *wildcarded* `@source` glob through
 * .gitignore, so `@source 'extensions/*​/*​/...'` silently matches nothing. Only
 * a concrete directory path followed by a file glob gets through — which is why
 * this has to be one explicit line per extension.
 *
 * Writing those lines by hand meant editing the core on every install, and the
 * list drifted: six installed extensions were missing from it, so their unique
 * utilities were never generated. This discovers them at build time instead.
 */
export default function extensionTailwindSources(projectRoot) {
    return {
        name: 'hybridcore-extension-tailwind-sources',

        // Must run before @tailwindcss/vite reads the stylesheet.
        enforce: 'pre',

        transform(code, id) {
            const path = id.replace(/\\/g, '/').split('?')[0];

            if (!path.endsWith('resources/css/app.css') || !code.includes(MARKER)) {
                return null;
            }

            const sources = discover(projectRoot)
                .map((dir) => `@source '../../${dir}/resources/js/**/*.{vue,ts}';`)
                .join('\n');

            return { code: code.replace(MARKER, sources), map: null };
        },
    };
}

/**
 * Installed extensions that ship a frontend, as `extensions/<vendor>/<name>`.
 *
 * extension.json is the same manifest the PHP side discovers, so an extension
 * is picked up here exactly when the platform considers it installed.
 */
function discover(projectRoot) {
    const base = join(projectRoot, 'extensions');
    const found = [];

    if (!existsSync(base)) {
        return found;
    }

    for (const vendor of dirs(base)) {
        for (const name of dirs(join(base, vendor))) {
            const dir = join(base, vendor, name);

            if (existsSync(join(dir, 'extension.json')) && existsSync(join(dir, 'resources/js'))) {
                found.push(`extensions/${vendor}/${name}`);
            }
        }
    }

    return found.sort();
}

function dirs(path) {
    try {
        return readdirSync(path, { withFileTypes: true })
            .filter((entry) => entry.isDirectory())
            .map((entry) => entry.name);
    } catch {
        return [];
    }
}
