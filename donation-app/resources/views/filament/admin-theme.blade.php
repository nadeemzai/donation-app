{{--
    Admin colours (Imdaad brand + one bright colour per area). Added to every panel page by
    AdminPanelProvider (HEAD_END hook). Plain CSS on Filament's own class names — no build step.
    Colours live in App\Support\AdminPalette.
--}}
@php use App\Support\AdminPalette; @endphp
<style>
    :root {
        --imd-crimson: {{ AdminPalette::CRIMSON }};
        --imd-plum: #4c1d95;
        --imd-sidebar: linear-gradient(180deg, #9f1239 0%, #7e1d55 45%, #4c1d95 100%);
        --imd-rainbow: linear-gradient(90deg, #e5254b, #f59e0b, #10b981, #0ea5e9, #8b5cf6, #ec4899);
    }

    /* ── page background ─────────────────────────────────────────── */
    .fi-body { background: linear-gradient(160deg, #fff1f3 0%, #f5f3ff 45%, #ecfeff 100%) fixed; }
    .dark .fi-body { background: linear-gradient(160deg, #1a0b12 0%, #120f24 50%, #071a1f 100%) fixed; }

    /* ── sidebar: crimson → plum ─────────────────────────────────── */
    .fi-sidebar { background: var(--imd-sidebar) !important; border-inline-end: 0 !important; }
    .fi-sidebar-header { background: rgba(0, 0, 0, 0.12) !important; border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important; box-shadow: none !important; }
    .fi-sidebar-header a, .fi-sidebar-header .fi-logo, .fi-sidebar-header button { color: #fff !important; }
    .fi-sidebar .fi-sidebar-group-label { color: rgba(255, 255, 255, 0.65) !important; letter-spacing: 0.06em; text-transform: uppercase; font-size: 0.7rem; }
    .fi-sidebar .fi-sidebar-group-btn svg, .fi-sidebar .fi-sidebar-group-collapse-btn { color: rgba(255, 255, 255, 0.7) !important; }
    .fi-sidebar .fi-sidebar-item-label { color: rgba(255, 255, 255, 0.92) !important; }
    .fi-sidebar .fi-sidebar-item-btn { border-radius: 0.75rem; transition: background 0.15s; }
    .fi-sidebar .fi-sidebar-item-btn:hover { background: rgba(255, 255, 255, 0.12) !important; }
    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn { background: rgba(255, 255, 255, 0.95) !important; box-shadow: 0 6px 18px -8px rgba(0, 0, 0, 0.5); }
    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label { color: #9f1239 !important; font-weight: 700; }
    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-icon { color: var(--imd-crimson) !important; }
    .fi-sidebar .fi-sidebar-item-grouped-border-part { background: rgba(255, 255, 255, 0.35) !important; }
    .fi-sidebar .fi-sidebar-footer, .fi-sidebar .fi-sidebar-database-notifications-btn { color: #fff !important; }
    .fi-sidebar .fi-sidebar-database-notifications-btn-label { color: #fff !important; }
@foreach (AdminPalette::GROUPS as $group => $colour)
    .fi-sidebar [data-group-label="{{ $group }}"] .fi-sidebar-item:not(.fi-active) .fi-sidebar-item-icon { color: {{ $colour }} !important; }
@endforeach
    .fi-sidebar .fi-sidebar-item:not(.fi-active) .fi-sidebar-item-icon { color: #fda4af; }

    /* ── top bar: white with a rainbow edge ──────────────────────── */
    .fi-topbar { position: relative; background: rgba(255, 255, 255, 0.85) !important; backdrop-filter: blur(8px); box-shadow: none !important; }
    .dark .fi-topbar { background: rgba(17, 12, 28, 0.85) !important; }
    .fi-topbar::after { content: ""; position: absolute; inset-inline: 0; bottom: 0; height: 3px; background: var(--imd-rainbow); }

    /* ── headings ────────────────────────────────────────────────── */
    .fi-header-heading { background: linear-gradient(90deg, #be123c, #7c3aed); -webkit-background-clip: text; background-clip: text; color: transparent !important; }

    /* ── sections & widgets: soft cards with a coloured top edge ─── */
    .fi-section { border-radius: 1.25rem !important; box-shadow: 0 10px 30px -18px rgba(76, 29, 149, 0.35) !important; overflow: hidden; }
    .fi-wi-widget .fi-section, .fi-wi-chart .fi-section { border-top: 4px solid transparent !important; border-image: var(--imd-rainbow) 1 !important; border-image-slice: 1 !important; }
    .fi-wi-widget .fi-section-header-heading, .fi-wi-chart .fi-section-header-heading { color: #9f1239; font-weight: 700; }
    .dark .fi-wi-widget .fi-section-header-heading, .dark .fi-wi-chart .fi-section-header-heading { color: #fda4af; }

    /* ── stat cards: full-colour gradients ───────────────────────── */
    .imd-stat { position: relative; overflow: hidden; border: 0 !important; border-radius: 1.25rem !important; box-shadow: 0 14px 30px -16px rgba(0, 0, 0, 0.45) !important; transition: transform 0.15s; }
    .imd-stat:hover { transform: translateY(-2px); }
    .imd-stat::before { content: ""; position: absolute; width: 9rem; height: 9rem; right: -2.5rem; top: -3rem; border-radius: 9999px; background: rgba(255, 255, 255, 0.14); pointer-events: none; }
    .imd-stat .fi-wi-stats-overview-stat-label, .imd-stat .fi-wi-stats-overview-stat-value { color: #fff !important; }
    .imd-stat .fi-wi-stats-overview-stat-label { opacity: 0.9; font-weight: 600; }
    .imd-stat .fi-wi-stats-overview-stat-label-ctn svg { color: rgba(255, 255, 255, 0.95) !important; }
    .imd-stat .fi-wi-stats-overview-stat-description, .imd-stat .fi-wi-stats-overview-stat-description * { color: rgba(255, 255, 255, 0.92) !important; }
    .imd-stat .fi-wi-stats-overview-stat-chart-bg-color { color: rgba(255, 255, 255, 0.18) !important; }
    .imd-stat .fi-wi-stats-overview-stat-chart-border-color { color: rgba(255, 255, 255, 0.85) !important; }
@foreach (AdminPalette::TONES as $tone => [$from, $to])
    .imd-stat-{{ $tone }} { background: linear-gradient(135deg, {{ $from }} 0%, {{ $to }} 100%) !important; }
@endforeach

    /* ── tables: coloured header row ─────────────────────────────── */
    .fi-ta-header-cell { background: linear-gradient(90deg, rgba(229, 37, 75, 0.06), rgba(139, 92, 246, 0.06)) !important; }

    /* ── login & other simple pages ──────────────────────────────── */
    .fi-simple-layout { background: radial-gradient(circle at 15% 20%, rgba(245, 158, 11, 0.35), transparent 40%), radial-gradient(circle at 85% 80%, rgba(14, 165, 233, 0.35), transparent 40%), var(--imd-sidebar) !important; }
    .fi-simple-main { border-radius: 1.5rem !important; box-shadow: 0 30px 60px -20px rgba(0, 0, 0, 0.55) !important; }
    .fi-simple-header-heading { color: #9f1239 !important; }
</style>
