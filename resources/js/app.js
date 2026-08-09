import 'bootstrap';

import Alpine from 'alpinejs';

import './course-select.js';
import { initLoadingSubmitForms, setButtonLoading } from './button-loading.js';

window.Alpine = Alpine;
window.PneButtonLoading = { setButtonLoading, initLoadingSubmitForms };

document.addEventListener('DOMContentLoaded', () => {
    initLoadingSubmitForms();
});

/**
 * Bootstrap czasem zostawia .modal-backdrop po zamknięciu / przełączaniu modali
 * (np. lista ankiet → edycja). Usuń osierocone warstwy, gdy żadne okno nie jest otwarte.
 */
document.addEventListener('hidden.bs.modal', () => {
    requestAnimationFrame(() => {
        if (document.querySelector('.modal.show')) {
            return;
        }

        document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    });
});

Alpine.start();
