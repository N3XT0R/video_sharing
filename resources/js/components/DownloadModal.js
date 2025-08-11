export default class DownloadModal {
    constructor() {
        this.modal = document.createElement('div');
        this.modal.id = 'downloadModal';
        this.modal.style.cssText = 'display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;';
        this.modal.innerHTML = `
            <div class="panel" style="max-width:400px;width:90%;">
                <h3>Download läuft...</h3>
                <ul id="downloadFileList" style="margin:12px 0 16px 18px; list-style:disc;"></ul>
                <div class="w-full h-2 bg-gray-200 rounded overflow-hidden">
                    <div id="zipProgressBar" class="h-full w-0 bg-blue-500 transition-all"></div>
                </div>
                <button type="button" id="closeModal" class="btn" style="margin-top:16px; display:none;">Schließen</button>
            </div>`;
        document.body.appendChild(this.modal);
        this.fileList = this.modal.querySelector('#downloadFileList');
        this.progressBar = this.modal.querySelector('#zipProgressBar');
        this.closeBtn = this.modal.querySelector('#closeModal');
    }

    open(files = []) {
        this.fileList.innerHTML = '';
        files.forEach(name => {
            const li = document.createElement('li');
            li.textContent = name;
            this.fileList.appendChild(li);
        });
        this.progressBar.style.width = '0%';
        this.closeBtn.style.display = 'none';
        this.modal.style.display = 'flex';
    }

    update(progress) {
        this.progressBar.style.width = `${progress}%`;
    }

    showClose() {
        this.closeBtn.style.display = 'block';
    }

    onClose(cb) {
        this.closeBtn.addEventListener('click', () => {
            this.modal.style.display = 'none';
            if (cb) cb();
        });
    }
}
