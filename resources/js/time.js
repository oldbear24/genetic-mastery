// Times are stored in UTC and rendered on the server in a fallback time
// zone. Here every <time data-local> is re-rendered in the browser's own
// time zone as "14. 11. 2026 14:20" (NF-4).
const formatter = new Intl.DateTimeFormat('cs-CZ', {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

export function formatLocal(date) {
    return formatter.format(date);
}

export function localizeTimes(root) {
    root.querySelectorAll('time[data-local][datetime]').forEach((el) => {
        const date = new Date(el.getAttribute('datetime'));
        if (Number.isNaN(date.getTime())) {
            return;
        }
        const text = formatLocal(date);
        if (el.dataset.local === 'title') {
            el.title = text;
        } else {
            el.textContent = text;
        }
    });
}
