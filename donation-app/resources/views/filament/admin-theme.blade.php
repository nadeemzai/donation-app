{{--
    Dashboard colours: gradient stat cards and colourful widget sections. Added to the dashboard
    page only (AdminPanelProvider, HEAD_END hook scoped to Dashboard) — the menu and every other
    screen keep the standard look. Colours live in App\Support\AdminPalette.
--}}
@php use App\Support\AdminPalette; @endphp
<style>
    /* ── widget sections: a rainbow top edge ─────────────────────── */
    .fi-wi-widget .fi-section, .fi-wi-chart .fi-section { border-radius: 1.25rem !important; border-top: 4px solid transparent !important; border-image: linear-gradient(90deg, #e5254b, #f59e0b, #10b981, #0ea5e9, #8b5cf6, #ec4899) 1 !important; }

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
</style>
