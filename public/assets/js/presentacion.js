'use strict';

// Mejora progresiva de presentación. No envía formularios ni modifica datos de negocio.
(() => {
    const shell = document.querySelector('.app-shell');
    const panel = document.querySelector('#admin-navigation-panel');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const close = document.querySelector('[data-sidebar-close]');
    const mobileHeader = document.querySelector('[data-sidebar-mobile-header]');
    const backdrop = document.querySelector('[data-nav-backdrop]');
    const header = shell?.querySelector('.topbar');
    const main = document.querySelector('#contenido');
    if (shell && panel && toggle && close && mobileHeader && backdrop && header && main) {
        const mobile = window.matchMedia('(max-width: 767px)');
        let collapsed = false;
        let opened = false;
        let previousFocus = null;
        try { collapsed = window.localStorage.getItem('sfatsa.sidebar.collapsed') === 'true'; } catch { /* Preferencia opcional. */ }

        const syncToggle = () => {
            const expanded = mobile.matches ? opened : !collapsed;
            toggle.setAttribute('aria-expanded', String(expanded));
            toggle.setAttribute('aria-label', mobile.matches
                ? (opened ? 'Cerrar menú de navegación' : 'Abrir menú de navegación')
                : (collapsed ? 'Expandir menú de navegación' : 'Contraer menú de navegación'));
            shell.dataset.sidebarCollapsed = String(collapsed);
        };
        const closePanel = (restore = true) => {
            opened = false;
            shell.dataset.navigationOpen = 'false';
            document.body.classList.remove('navigation-open');
            backdrop.hidden = true;
            main.inert = false;
            header.inert = false;
            panel.inert = mobile.matches;
            panel.removeAttribute('role');
            panel.removeAttribute('aria-modal');
            syncToggle();
            if (restore && previousFocus instanceof HTMLElement && previousFocus.isConnected) previousFocus.focus();
            previousFocus = null;
        };
        const openPanel = () => {
            if (!mobile.matches) return;
            previousFocus = document.activeElement;
            opened = true;
            panel.inert = false;
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-modal', 'true');
            backdrop.hidden = false;
            shell.dataset.navigationOpen = 'true';
            document.body.classList.add('navigation-open');
            syncToggle();
            // Mover el foco antes de volver inerte el encabezado que contenía el botón.
            panel.getBoundingClientRect();
            close.focus({ preventScroll: true });
            main.inert = true;
            header.inert = true;
        };
        const configureViewport = () => {
            closePanel(false);
            mobileHeader.hidden = !mobile.matches;
        };
        toggle.addEventListener('click', () => {
            if (mobile.matches) {
                if (opened) closePanel(); else openPanel();
            } else {
                collapsed = !collapsed;
                try { window.localStorage.setItem('sfatsa.sidebar.collapsed', String(collapsed)); } catch { /* Sin almacenamiento, funciona igualmente. */ }
                syncToggle();
            }
        });
        close.addEventListener('click', () => closePanel());
        backdrop.addEventListener('click', () => closePanel());
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (opened) { event.preventDefault(); closePanel(); }
                else shell.dataset.tooltipsMuted = 'true';
            }
            if (!opened || event.key !== 'Tab') return;
            const focusable = [...panel.querySelectorAll('a[href], button:not([disabled]), [tabindex="0"]')]
                .filter((element) => !element.hidden && element.getClientRects().length > 0);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (!first) { event.preventDefault(); panel.focus(); return; }
            if (event.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) {
                event.preventDefault(); first.focus();
            }
        });
        panel.addEventListener('focusout', () => { delete shell.dataset.tooltipsMuted; });
        panel.addEventListener('pointerleave', () => { delete shell.dataset.tooltipsMuted; });
        mobile.addEventListener('change', configureViewport);
        window.addEventListener('pageshow', configureViewport);
        configureViewport();
        toggle.hidden = false;
        document.documentElement.classList.add('ui-enhanced');
    }

    // Solo páginas de confirmación simple marcadas expresamente; nunca revisiones de altas.
    const card = document.querySelector('[data-simple-confirm]');
    const form = card?.querySelector('form[method="post"]');
    const cancel = card?.querySelector('a.button--secondary[href]');
    if (!card || !form || !cancel || typeof HTMLDialogElement === 'undefined') return;
    const title = card.querySelector('h2');
    if (!title?.id) return;
    const consequence = card.querySelector('p');
    const subject = document.querySelector('.details-card h2')?.textContent?.trim();
    const dialog = document.createElement('dialog');
    dialog.className = 'simple-confirm-dialog';
    dialog.setAttribute('aria-labelledby', title.id);
    dialog.setAttribute('tabindex', '-1');
    const descriptions = [];
    if (subject) {
        const identity = document.createElement('p');
        identity.id = title.id + '-subject';
        identity.className = 'confirm-subject';
        identity.textContent = subject;
        title.after(identity);
        descriptions.push(identity.id);
    }
    if (consequence) {
        if (!consequence.id) consequence.id = title.id + '-consequence';
        descriptions.push(consequence.id);
    }
    if (descriptions.length) dialog.setAttribute('aria-describedby', descriptions.join(' '));
    card.before(dialog);
    dialog.append(card);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        // El mismo destino que Cancelar; sin enviar POST ni consumir tokens.
        window.location.assign(cancel.href);
    });
    try {
        dialog.showModal();
        const error = card.querySelector('[role="alert"]');
        if (error) { error.setAttribute('tabindex', '-1'); error.focus(); }
        else cancel.focus();
    } catch {
        // Navegadores sin soporte completo conservan la página original utilizable.
        dialog.before(card);
        dialog.remove();
    }
})();
