// Alpine components (CSP build: logic lives here, not in HTML attributes).

export function registerComponents(Alpine) {
    // Simple disclosure: mobile navigation, collapsible panels.
    Alpine.data('disclosure', () => ({
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
    }));
}
