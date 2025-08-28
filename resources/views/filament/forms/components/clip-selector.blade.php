<div
        x-data="{
        start: 0,
        end: 0,
        duration: 0,
        showPlayer: false,
        init() {
            const input = this.$el.parentElement.querySelector('input[type=file][wire\:model$=file]');
            input?.addEventListener('change', () => {
                // hide preview while uploading
                this.showPlayer = false;
            });
            input?.addEventListener('livewire-upload-finish', () => {
                const file = input.files[0];
                if (file) {
                    this.$refs.video.src = URL.createObjectURL(file);
                    this.showPlayer = true;
                }
            });
            this.$refs.video.addEventListener('loadedmetadata', () => {
                this.duration = this.$refs.video.duration;
                if (this.end === 0 || this.end > this.duration) {
                    this.end = this.duration;
                }
                if (typeof noUiSlider !== 'undefined') {
                    if (this.$refs.slider.noUiSlider) {
                        this.$refs.slider.noUiSlider.destroy();
                    }
                    noUiSlider.create(this.$refs.slider, {
                        start: [this.start, this.end],
                        connect: true,
                        range: { min: 0, max: this.duration },
                    });
                    this.$refs.slider.noUiSlider.on('update', (values) => {
                        this.start = Math.round(values[0]);
                        this.end = Math.round(values[1]);
                    });
                }
            });
        }
    }"
        class="space-y-2"
>
    <video x-ref="video" x-show="showPlayer" x-cloak controls class="w-full"></video>
    <input type="hidden" x-model="start" wire:model="start_sec">
    <input type="hidden" x-model="end" wire:model="end_sec">

    <div x-ref="slider" class="mt-2"></div>

    <div class="text-sm">
        Start: <span x-text="start"></span>s –
        Ende: <span x-text="end"></span>s
    </div>
</div>
