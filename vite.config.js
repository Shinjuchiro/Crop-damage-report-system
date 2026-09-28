import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // The bunny('Instrument Sans') font config that used to sit here
            // was emitting nothing: the built pages carried no font link and
            // registered no @font-face, so the font never arrived. It is
            // replaced by a self-hosted Inter import in resources/css/app.css,
            // which also removes the runtime dependency on fonts.bunny.net -
            // worth having in a PWA that is used on a weak mobile signal.
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
