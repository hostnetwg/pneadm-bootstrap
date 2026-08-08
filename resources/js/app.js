import 'bootstrap';

import Alpine from 'alpinejs';

import './course-select.js';
import { initLoadingSubmitForms, setButtonLoading } from './button-loading.js';

window.Alpine = Alpine;
window.PneButtonLoading = { setButtonLoading, initLoadingSubmitForms };

document.addEventListener('DOMContentLoaded', () => {
    initLoadingSubmitForms();
});

Alpine.start();
