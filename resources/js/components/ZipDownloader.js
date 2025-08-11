import axios from 'axios';
import DownloadModal from './DownloadModal';

export default class ZipDownloader {
    constructor(form) {
        this.form = form;
        this.selectAllBtn = document.getElementById('selectAll');
        this.selectNoneBtn = document.getElementById('selectNone');
        this.submitBtn = document.getElementById('zipSubmit');
        this.selCountEl = document.getElementById('selCount');

        this.modal = new DownloadModal();
        this.modal.onClose(() => {});

        this.init();
    }

    init() {
        this.updateCount();
        this.selectAllBtn?.addEventListener('click', () => this.toggleAll(true));
        this.selectNoneBtn?.addEventListener('click', () => this.toggleAll(false));
        document.addEventListener('change', e => {
            if (e.target && e.target.classList?.contains('pickbox')) {
                this.updateCount();
            }
        });
        this.submitBtn?.addEventListener('click', () => this.startDownload());
    }

    toggleAll(state) {
        document.querySelectorAll('.pickbox').forEach(cb => cb.checked = state);
        this.updateCount();
    }

    updateCount() {
        const n = document.querySelectorAll('.pickbox:checked').length;
        if (this.selCountEl) this.selCountEl.textContent = `${n} ausgewählt`;
    }

    async startDownload() {
        const boxes = Array.from(document.querySelectorAll('.pickbox:checked'));
        const selected = boxes.map(cb => cb.value);
        if (!selected.length) {
            alert('Bitte wähle mindestens ein Video aus.');
            return;
        }

        const files = boxes.map(cb => cb.closest('.card')?.querySelector('.file-name')?.textContent?.trim()).filter(Boolean);
        this.modal.open(files);

        const postUrl = this.form.dataset.zipPostUrl;
        const token = document
            .querySelector('meta[name="csrf-token"]')
            .getAttribute('content');
        const {
            data: { jobId }
        } = await axios.post(
            postUrl,
            { assignment_ids: selected },
            {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token
                }
            }
        );

        let downloading = false;
        const poll = setInterval(async () => {
            const { data: r } = await axios.get(`/zips/${jobId}/progress`);
            this.modal.update(r.progress || 0, r.status);
            if (r.status === 'ready' && !downloading) {
                downloading = true;
                clearInterval(poll);
                await this.downloadZip(jobId);
            }
        }, 500);
    }

    async downloadZip(jobId) {
        const response = await axios.get(`/zips/${jobId}/download`, {
            responseType: 'blob'
        });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', '');
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        this.modal.showClose();
    }
}
