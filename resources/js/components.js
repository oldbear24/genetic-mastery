// Alpine components (CSP build: logic lives here, not in HTML attributes).

const ALLOWED_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

function readJson(id) {
    const el = document.getElementById(id);
    return el ? JSON.parse(el.textContent) : null;
}

function formatMb(bytes) {
    return (bytes / 1024 / 1024).toLocaleString('cs-CZ', { maximumFractionDigits: 1, minimumFractionDigits: 1 }) + ' MB';
}

function guessKind(name, index) {
    const lower = name.toLowerCase();
    if (/(ancest|mutac|mutat|predc|předc|lineage)/.test(lower)) return 'ancestry';
    if (/stat/.test(lower)) return 'stats';
    return index === 0 ? 'stats' : index === 1 ? 'ancestry' : 'other';
}

export function registerComponents(Alpine) {
    // Simple disclosure: collapsible panels.
    Alpine.data('disclosure', () => ({
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
    }));

    // SUB-1: pick species, then stat. Verified stats cannot be picked.
    Alpine.data('claimPicker', () => ({
        species: '',
        milestoneId: '',
        milestones: {},
        bySpecies: {},

        init() {
            const data = readJson('claim-data') || {};
            this.milestones = data.milestones || {};
            this.bySpecies = data.bySpecies || {};
            this.species = data.species || '';
            this.milestoneId = data.selected ? String(data.selected) : '';
            this.$watch('species', (value) => this.selectFirstOpen(value));
        },

        selectFirstOpen(species) {
            const current = this.milestones[this.milestoneId];
            if (current && current.species === species && current.open) {
                return;
            }
            const first = (this.bySpecies[species] || []).find((id) => this.milestones[id].open);
            this.milestoneId = first ? String(first) : '';
        },

        isSpecies(code) {
            return this.species === code;
        },

        get hasSelection() {
            return this.milestoneId !== '' && !!this.milestones[this.milestoneId];
        },

        get selectedCode() {
            return this.hasSelection ? this.milestones[this.milestoneId].code : '';
        },

        get selectedLabel() {
            return this.hasSelection ? this.milestones[this.milestoneId].label : '';
        },
    }));

    // SUB-2: 1–10 screenshots with previews and a type for each.
    Alpine.data('evidenceUpload', () => ({
        files: [],
        errors: [],
        dragging: false,
        maxFiles: 10,
        maxBytes: 15 * 1024 * 1024,
        messages: {},
        nextId: 1,

        init() {
            const config = readJson('upload-config') || {};
            this.maxFiles = config.maxFiles || this.maxFiles;
            this.maxBytes = (config.maxMb || 15) * 1024 * 1024;
            this.messages = config.messages || {};
        },

        browse() {
            this.$refs.input.click();
        },

        picked(event) {
            this.add(Array.from(event.target.files || []), true);
        },

        dragover() {
            this.dragging = true;
        },

        dragleave() {
            this.dragging = false;
        },

        dropped(event) {
            this.dragging = false;
            this.add(Array.from(event.dataTransfer ? event.dataTransfer.files : []), false);
        },

        add(incoming, fromInput) {
            this.errors = [];
            // When the input itself changed, it now holds only the new pick:
            // the kept list is rebuilt from this.files below.
            for (const file of incoming) {
                if (!ALLOWED_TYPES.includes(file.type)) {
                    this.errors.push(this.message('wrongType', file.name));
                    continue;
                }
                if (file.size > this.maxBytes) {
                    this.errors.push(this.message('tooLarge', file.name));
                    continue;
                }
                if (this.files.length >= this.maxFiles) {
                    this.errors.push(this.message('tooMany', file.name));
                    break;
                }
                const entry = {
                    id: this.nextId++,
                    file,
                    name: file.name,
                    size: formatMb(file.size),
                    dimensions: '',
                    url: URL.createObjectURL(file),
                    kind: guessKind(file.name, this.files.length),
                };
                this.files.push(entry);
                this.readDimensions(entry);
            }
            this.sync();
            if (fromInput && this.errors.length === 0) {
                this.$refs.input.blur();
            }
        },

        readDimensions(entry) {
            const img = new Image();
            img.onload = () => {
                const found = this.files.find((f) => f.id === entry.id);
                if (found) {
                    found.dimensions = img.naturalWidth + ' × ' + img.naturalHeight;
                }
            };
            img.src = entry.url;
        },

        remove(event) {
            const id = Number(event.currentTarget.dataset.id);
            const entry = this.files.find((f) => f.id === id);
            if (entry) {
                URL.revokeObjectURL(entry.url);
            }
            this.files = this.files.filter((f) => f.id !== id);
            this.errors = [];
            this.sync();
        },

        // Keep the real <input type=file> in the same order as the list, so
        // images[i] matches image_types[i] on the server.
        sync() {
            const transfer = new DataTransfer();
            this.files.forEach((f) => transfer.items.add(f.file));
            this.$refs.input.files = transfer.files;
        },

        message(key, name) {
            return (this.messages[key] || key).replace(':name', name);
        },

        get hasFiles() {
            return this.files.length > 0;
        },

        get hasErrors() {
            return this.errors.length > 0;
        },
    }));

    // SUB-3: character counter for the note.
    Alpine.data('charCounter', () => ({
        count: 0,
        init() {
            this.count = this.$refs.field.value.length;
        },
        update() {
            this.count = this.$refs.field.value.length;
        },
    }));

    // Prevent double submits of long upload forms.
    Alpine.data('submitOnce', () => ({
        submitting: false,
        submit(event) {
            if (this.submitting) {
                event.preventDefault();
                return;
            }
            this.submitting = true;
        },
    }));

    // Zoom for evidence (ADM-2, HIS-1). Links work without JS as plain links.
    Alpine.data('lightbox', () => ({
        isOpen: false,
        src: '',
        alt: '',
        show(event) {
            const link = event.currentTarget;
            this.src = link.getAttribute('href');
            this.alt = link.dataset.alt || '';
            this.isOpen = true;
            this.$nextTick(() => this.$refs.close && this.$refs.close.focus());
        },
        close() {
            this.isOpen = false;
            this.src = '';
        },
    }));
}
