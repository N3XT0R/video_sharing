import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('zipForm');
    if (!form) return;

    const selectAllBtn = document.getElementById('selectAll');
    const selectNoneBtn = document.getElementById('selectNone');
    const submitBtn = document.getElementById('zipSubmit');
    const selCountEl = document.getElementById('selCount');
    const progressBar = document.getElementById('zipProgressBar');

    function updateCount() {
        const n = document.querySelectorAll('.pickbox:checked').length;
        if (selCountEl) selCountEl.textContent = `${n} ausgewählt`;
    }

    function toggleAll(state) {
        document.querySelectorAll('.pickbox').forEach(cb => {
            cb.checked = state;
        });
        updateCount();
    }

    selectAllBtn?.addEventListener('click', () => toggleAll(true));
    selectNoneBtn?.addEventListener('click', () => toggleAll(false));

    document.addEventListener('change', e => {
        if (e.target && e.target.classList?.contains('pickbox')) {
            updateCount();
        }
    });

    updateCount();

    submitBtn?.addEventListener('click', async () => {
        const selected = Array.from(document.querySelectorAll('.pickbox:checked')).map(cb => cb.value);
        if (selected.length === 0) {
            alert('Bitte wähle mindestens ein Video aus.');
            return;
        }

        const postUrl = form.dataset.zipPostUrl;
        const res = await fetch(postUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ assignment_ids: selected })
        });
        const { id } = await res.json();

        const t = setInterval(async () => {
            const r = await (await fetch(`/zips/${id}/progress`)).json();
            if (progressBar) progressBar.style.width = `${r.progress || 0}%`;
            if (r.status === 'ready') {
                clearInterval(t);
                window.location = `/zips/${id}/download`;
            }
        }, 500);
    });
});
