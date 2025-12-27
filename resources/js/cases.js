import './shared/bootstrap';
import { createApp } from 'vue';

import './cases/scripts/custom-swiper.js';


// Lazy loading: компоненты загружаются только когда нужны
const components = import.meta.glob('./cases/components/**/*.vue');

// Собираем реестр: header -> () => import('./cases/components/Header.vue')
const registry = {};
for (const path in components) {
    const name = path.split('/').pop().replace('.vue', '');
    const kebabName = name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
    registry[kebabName] = components[path];
}

// Автоматическое монтирование по data-vue-component
document.addEventListener('DOMContentLoaded', () => {

    document.querySelectorAll('[data-vue-component]').forEach(async el => {
        const componentName = el.dataset.vueComponent;
        const loader = registry[componentName];

        if (!loader) {
            console.warn(`Vue component "${componentName}" not found`);
            return;
        }

        // Загружаем компонент
        const module = await loader();

        // Собираем props из data-* атрибутов
        const props = {};
        for (const key in el.dataset) {
            if (key === 'vueComponent') continue;
            try {
                props[key] = JSON.parse(el.dataset[key]);
            } catch {
                props[key] = el.dataset[key];
            }
        }

        createApp(module.default, props).mount(el);
    });
});
