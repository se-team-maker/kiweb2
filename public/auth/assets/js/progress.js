document.addEventListener('DOMContentLoaded', () => {
    let progressBar = document.getElementById('page-progress');
    const container = document.querySelector('.login-card') ||
        document.querySelector('.verification-card') ||
        document.querySelector('.dashboard-container') ||
        document.body;
    if (!progressBar) {
        progressBar = document.createElement('div');
        progressBar.id = 'page-progress';
        progressBar.className = 'page-progress';
        const bar = document.createElement('span');
        bar.className = 'bar';
        progressBar.appendChild(bar);
    }
    progressBar.setAttribute('role', 'progressbar');
    progressBar.setAttribute('aria-label', '読み込んでいます');

    let scrim = document.getElementById('page-scrim');
    if (!scrim) {
        scrim = document.createElement('div');
        scrim.id = 'page-scrim';
        scrim.className = 'kPY6ve';
        scrim.hidden = true;
        document.body.appendChild(scrim);
    }
    if (progressBar.parentElement !== container) {
        container.insertBefore(progressBar, container.firstChild);
    }

    let timer = null;
    const transitionDelayMs = 500;

    const triggerProgress = () => {
        progressBar.classList.remove('active');
        if (timer) {
            clearTimeout(timer);
        }
        scrim.hidden = false;
        // reset animation
        void progressBar.offsetWidth;
        progressBar.classList.add('active');
        timer = setTimeout(() => {
            progressBar.classList.remove('active');
            scrim.hidden = true;
        }, transitionDelayMs);
    };

    const shouldIgnore = (element) => {
        if (!element) {
            return true;
        }
        if (element.closest('.lang-menu')) {
            return true;
        }
        if (element.closest('[data-progress="off"]')) {
            return true;
        }
        return false;
    };

    const getTriggerElement = (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return null;
        }
        return target.closest('a');
    };

    const handleTrigger = (event) => {
        const trigger = getTriggerElement(event);
        if (!trigger || shouldIgnore(trigger)) {
            return;
        }
        const anchor = trigger.closest('a');
        if (anchor && anchor.href && !anchor.hasAttribute('download')) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                triggerProgress();
                return;
            }
            if (!anchor.target || anchor.target === '_self') {
                event.preventDefault();
                triggerProgress();
                setTimeout(() => {
                    window.location.href = anchor.href;
                }, transitionDelayMs);
                return;
            }
        }
    };

    document.addEventListener('click', handleTrigger);

    const startPageTransition = (url) => {
        triggerProgress();
        if (url) {
            setTimeout(() => {
                window.location.href = url;
            }, transitionDelayMs);
        }
    };

    window.startPageTransition = startPageTransition;

    document.addEventListener('page-transition', (event) => {
        const detail = event && event.detail ? event.detail : {};
        startPageTransition(detail.url || '');
    });

});
