<div
        x-data="{
        start: 0,
        end: 0,
        duration: 0,
        init() {
            const input = this.$el.parentElement.querySelector('input[type=file][wire\\:model$=file]');
            input?.addEventListener('change', e => {
                const file = e.target.files[0];
                if (file) {
                    this.$refs.video.src = URL.createObjectURL(file);
                }
            });
            this.$refs.video.addEventListener('loadedmetadata', () => {
                this.duration = this.$refs.video.duration;
                if (this.end === 0 || this.end > this.duration) {
                    this.end = this.duration;
                }
            });
        }
    }"
        class="space-y-2"
>
    <video x-ref="video" controls class="w-full"></video>
    <input type="hidden" x-model="start" wire:model="start_sec">
    <input type="hidden" x-model="end" wire:model="end_sec">

    <div class="flex items-center space-x-2">
        <input type="range" x-model="start" min="0" :max="duration" step="1" class="w-full">
        <input type="range" x-model="end" min="0" :max="duration" step="1" class="w-full">
    </div>

    <div class="text-sm">
        Start: <span x-text="start"></span>s –
        Ende: <span x-text="end"></span>s
    </div>
</div>
