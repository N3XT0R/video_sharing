import './bootstrap';
import noUiSlider from 'nouislider';
import 'nouislider/dist/nouislider.css';
import ZipDownloader from './components/ZipDownloader';

window.noUiSlider = noUiSlider;

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('zipForm');
    if (form) {
        new ZipDownloader(form);
    }
});
