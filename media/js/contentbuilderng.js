
// ContentBuilderNG — utilities

function is_numeric(value) {
    return (typeof value === 'number' || typeof value === 'string') && value !== '' && !isNaN(value);
}

// Fade animation using requestAnimationFrame
const _ = {
    fade(direction, el, onComplete, steps, interval) {
        const fadeIn = direction === 'in';
        const totalSteps = steps || 15;
        let step = 0;
        const tick = () => {
            step++;
            const ratio = step / totalSteps;
            el.style.opacity = String(fadeIn ? ratio : 1 - ratio);
            if (step < totalSteps) {
                setTimeout(tick, interval || 50);
            } else if (onComplete) {
                onComplete();
            }
        };
        tick();
    },
};
_.F = _.fade;

// Rating — fallback if RatingHelper.php does not provide its own version
function cbRetrieveRatingResults(payload, lastId) {
    let result;
    try {
        result = typeof payload === 'string' ? JSON.parse(payload) : payload;
    } catch (e) {
        return;
    }
    if (typeof result !== 'object' || result === null) {
        return;
    }
    if (typeof result.success !== 'undefined') {
        result = result.success ? (result.data || {}) : { code: 1, msg: result.message || '' };
    }
    const msgEl = document.getElementById(lastId || window.cbLastId);
    if (!msgEl) {
        return;
    }
    msgEl.style.display = 'block';
    msgEl.innerHTML = result.msg || '';
    const counterId = (lastId || window.cbLastId) + 'Counter';
    const counterEl = document.getElementById(counterId);
    if (result.code == 0 && counterEl && is_numeric(counterEl.innerHTML)) {
        counterEl.innerHTML = String(Number(counterEl.innerHTML) + 1);
    }
    _.fade('out', msgEl, () => { msgEl.style.display = 'none'; }, 40);
}

function cbRate(url, lastId) {
    window.cbLastId = lastId;
    fetch(url, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.text())
        .then(text => cbRetrieveRatingResults(text, lastId))
        .catch(() => {});
}

window.is_numeric = is_numeric;
window.cbRetrieveRatingResults = cbRetrieveRatingResults;
window.cbRate = cbRate;
