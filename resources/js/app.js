// Lets Blade reference these files with Vite::asset() (hashed file names, cached long-term).
// Eager "?url" imports so the build keeps them even though nothing here uses the result.
import.meta.glob('../images/**/*.svg', { eager: true, query: '?url', import: 'default' });
