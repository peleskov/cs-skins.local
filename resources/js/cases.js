import './shared/bootstrap';
import { createApp } from 'vue';

// Cases Vue компоненты
import Cases from './cases/components/Cases.vue';
import CaseDetails from './cases/components/CaseDetails.vue';

// Инициализация Vue компонентов
document.addEventListener('DOMContentLoaded', () => {
    // Cases компонент (список кейсов)
    const casesElement = document.getElementById('cases-app');
    if (casesElement) {
        const app = createApp(Cases, {
            initialCases: JSON.parse(casesElement.dataset.cases || '[]'),
            user: casesElement.dataset.user !== 'null' ? JSON.parse(casesElement.dataset.user) : null
        });
        app.mount('#cases-app');
    }

    // CaseDetails компонент (открытие кейса)
    const caseDetailsElement = document.getElementById('case-details-app');
    if (caseDetailsElement) {
        const app = createApp(CaseDetails, {
            initialCase: JSON.parse(caseDetailsElement.dataset.case || '{}'),
            caseSlug: caseDetailsElement.dataset.caseSlug || '',
            routes: JSON.parse(caseDetailsElement.dataset.routes || '{}')
        });
        app.mount('#case-details-app');
    }
});
