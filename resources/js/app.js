import './bootstrap';
import ZipDownloader from './components/ZipDownloader';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('zipForm');
    if (form) {
        new ZipDownloader(form);
    }
});
