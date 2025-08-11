export default class DownloadModal {
    constructor() {
        this.modal = document.createElement('div');
        this.modal.id = 'downloadModal';
        this.modal.style.cssText = 'display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;';
        this.modal.innerHTML = `
            <div class="panel" style="max-width:400px;width:90%;">
                <h3>Download läuft...</h3>
                <table class="w-full my-3 text-sm">
                    <thead>
                        <tr>
                            <th class="text-left">Video</th>
                            <th class="text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody id="downloadFileList"></tbody>
                </table>
                <p id="statusText" class="text-sm mb-2"></p>
                <div class="w-full h-2 bg-gray-200 rounded overflow-hidden">
                    <div id="zipProgressBar" class="h-full w-0 bg-blue-500 transition-all"></div>
                </div>
                <p id="progressText" class="text-right text-sm mt-1">0%</p>
                <button type="button" id="closeModal" class="btn mt-4 hidden">Schließen</button>
            </div>`;
        document.body.appendChild(this.modal);
        this.fileList = this.modal.querySelector('#downloadFileList');
        this.progressBar = this.modal.querySelector('#zipProgressBar');
        this.progressText = this.modal.querySelector('#progressText');
        this.statusText = this.modal.querySelector('#statusText');
        this.closeBtn = this.modal.querySelector('#closeModal');

        this.messages = {
            queued: 'Wartet...',
            preparing: 'Bereite Dateien vor...',
            downloading: 'Wird heruntergeladen...',
            downloaded: 'Heruntergeladen',
            packing: 'Wird gepackt...',
            ready: 'Fertig'
        };
    }

    open(files = []) {
        this.fileList.innerHTML = '';
        files.forEach(name => {
            const tr = document.createElement('tr');
            tr.dataset.name = name;
            const tdName = document.createElement('td');
            tdName.textContent = name;
            const tdStatus = document.createElement('td');
            tdStatus.textContent = this.messages.queued;
            tdStatus.classList.add('status');
            tr.appendChild(tdName);
            tr.appendChild(tdStatus);
            this.fileList.appendChild(tr);
        });
        this.progressBar.style.width = '0%';
        this.progressText.textContent = '0%';
        this.statusText.textContent = this.messages.queued;
        this.closeBtn.classList.add('hidden');
        this.modal.style.display = 'flex';
    }

    update(progress, status, files = {}) {
        this.progressBar.style.width = `${progress}%`;
        this.progressText.textContent = `${progress}%`;
        if (status) {
            const msg = this.messages[status] || status;
            this.statusText.textContent = msg;
        }
        Object.entries(files).forEach(([name, st]) => {
            let row = this.fileList.querySelector(`tr[data-name="${name}"]`);
            if (!row) {
                row = document.createElement('tr');
                row.dataset.name = name;
                const tdName = document.createElement('td');
                tdName.textContent = name;
                const tdStatus = document.createElement('td');
                tdStatus.classList.add('status');
                row.appendChild(tdName);
                row.appendChild(tdStatus);
                this.fileList.appendChild(row);
            }
            const tdStatus = row.querySelector('.status');
            tdStatus.textContent = this.messages[st] || st;
        });
    }

    showClose() {
        this.statusText.textContent = 'Fertig';
        this.closeBtn.classList.remove('hidden');
    }

    onClose(cb) {
        this.closeBtn.addEventListener('click', () => {
            this.modal.style.display = 'none';
            if (cb) cb();
        });
    }
}
