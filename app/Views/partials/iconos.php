<?php
// SVG locales de trazo uniforme: decorativos y acompañados siempre de texto.
$icono = static function (string $nombre): string {
    $trazos = [
        'panel-left' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 3v18m5-12 3 3-3 3"/>',
        'x' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="11" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="18" width="7" height="3" rx="1"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/>',
        'contact-round' => '<rect x="4" y="3" width="16" height="18" rx="4"/><path d="M2 8h3m-3 8h3m3 1a4 4 0 0 1 8 0"/><circle cx="12" cy="9" r="3"/>',
        'bus-front' => '<rect x="4" y="3" width="16" height="16" rx="3"/><path d="M4 11h16M8 3v8m8 0V3M6 19v2m12-2v2M8 15h.01M16 15h.01"/>',
        'route' => '<circle cx="6" cy="5" r="3"/><circle cx="18" cy="19" r="3"/><path d="M9 5h8a4 4 0 0 1 0 8H7a3 3 0 0 0 0 6h8"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9"/>',
        'shield-check' => '<path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6l-8-3Zm-4 9 3 3 5-6"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'pencil' => '<path d="m15 5 4 4M4 20l4-1L21 6a2.8 2.8 0 0 0-4-4L4 15l-1 6Z"/>',
        'trash' => '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>',
        'alert-triangle' => '<path d="m10.3 3.9-8 14a2 2 0 0 0 1.7 3h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0ZM12 9v4m0 4h.01"/>',
        'arrow-right' => '<path d="M4 12h16m-6-6 6 6-6 6"/>',
    ];
    if (!isset($trazos[$nombre])) { return ''; }
    return '<svg class="sfatsa-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" data-icon="' . $nombre . '">' . $trazos[$nombre] . '</svg>';
};
