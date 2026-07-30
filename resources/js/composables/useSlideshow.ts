import { onBeforeUnmount, onMounted, ref } from 'vue';

interface UseSlideshowOptions {
    /** Total number of slides. */
    total: number;
    /** Auto-advance duration (ms) per slide index; falls back to 6000ms. */
    durations: number[];
    /** Bind global keyboard shortcuts (space / arrows / R). Defaults to true. */
    keyboard?: boolean;
}

/**
 * Drives an auto-advancing slideshow: current index, play/pause state and a
 * 0..1 progress value for the active slide. This composable owns *only* the
 * navigation/timing concern — rendering is left entirely to the caller.
 */
export function useSlideshow({ total, durations, keyboard = true }: UseSlideshowOptions) {
    const index = ref(0);
    const playing = ref(true);
    const progress = ref(0);

    let raf = 0;
    let startedAt = 0;
    let elapsed = 0;

    const durationFor = (i: number) => durations[i] ?? 6000;

    const stop = () => {
        if (raf) cancelAnimationFrame(raf);
        raf = 0;
    };

    const tick = (now: number) => {
        if (!startedAt) startedAt = now;
        elapsed = now - startedAt;
        const pct = Math.min(elapsed / durationFor(index.value), 1);
        progress.value = pct;

        if (pct >= 1) {
            advance();
            return;
        }
        if (playing.value) raf = requestAnimationFrame(tick);
    };

    /** Restart the timer for the current slide. */
    const run = () => {
        stop();
        startedAt = 0;
        elapsed = 0;
        progress.value = 0;
        if (playing.value) raf = requestAnimationFrame(tick);
    };

    const setPlaying = (value: boolean) => {
        playing.value = value;
        if (value) {
            startedAt = performance.now() - elapsed;
            raf = requestAnimationFrame(tick);
        } else {
            stop();
        }
    };

    const advance = () => {
        if (index.value < total - 1) {
            index.value += 1;
            run();
        } else {
            // Hold on the final slide.
            setPlaying(false);
            progress.value = 1;
        }
    };

    /** Jump to a slide; jumping always resumes playback. */
    const go = (target: number) => {
        index.value = ((target % total) + total) % total;
        if (!playing.value) setPlaying(true);
        run();
    };

    const next = () => go(index.value + 1);
    const prev = () => go(index.value - 1);
    const toggle = () => setPlaying(!playing.value);

    const restart = () => {
        index.value = 0;
        setPlaying(true);
        run();
    };

    const onKeydown = (event: KeyboardEvent) => {
        if (event.code === 'Space') {
            event.preventDefault();
            toggle();
        } else if (event.code === 'ArrowRight') {
            next();
        } else if (event.code === 'ArrowLeft') {
            prev();
        } else if (event.key === 'r' || event.key === 'R') {
            restart();
        }
    };

    onMounted(() => {
        if (keyboard) window.addEventListener('keydown', onKeydown);
        run();
    });

    onBeforeUnmount(() => {
        if (keyboard) window.removeEventListener('keydown', onKeydown);
        stop();
    });

    return { index, playing, progress, go, next, prev, toggle, restart };
}
