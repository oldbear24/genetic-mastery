// Alpine CSP build: no eval, so the Content-Security-Policy can stay strict.
// Components are registered here with Alpine.data(); templates only refer
// to their properties and methods.
import Alpine from '@alpinejs/csp';
import { registerComponents } from './components';
import { localizeTimes } from './time';

registerComponents(Alpine);

window.Alpine = Alpine;
Alpine.start();

localizeTimes(document);
