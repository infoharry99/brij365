<?php

namespace App\Support;

final class Builder360Icon
{
    /**
     * @var array<string, string>
     */
    private const MAP = [
        'grid' => 'fa-table-cells-large',
        'check' => 'fa-check',
        'bell' => 'fa-bell',
        'chart' => 'fa-chart-column',
        'bar' => 'fa-chart-simple',
        'tasks' => 'fa-list-check',
        'calClock' => 'fa-calendar-days',
        'calendar' => 'fa-calendar-days',
        'bubble' => 'fa-comment',
        'chat' => 'fa-comment',
        'mail' => 'fa-envelope',
        'users' => 'fa-user-group',
        'user' => 'fa-user',
        'filter' => 'fa-filter',
        'funnel' => 'fa-filter',
        'tag' => 'fa-tag',
        'mega' => 'fa-bullhorn',
        'rupee' => 'fa-indian-rupee-sign',
        'star' => 'fa-star',
        'building' => 'fa-building',
        'layers' => 'fa-layer-group',
        'spark' => 'fa-wand-magic-sparkles',
        'trend' => 'fa-arrow-trend-up',
        'hardhat' => 'fa-helmet-safety',
        'box' => 'fa-box',
        'package' => 'fa-box',
        'shuffle' => 'fa-shuffle',
        'cart' => 'fa-cart-shopping',
        'file' => 'fa-file',
        'truck' => 'fa-truck',
        'wrench' => 'fa-wrench',
        'ruler' => 'fa-ruler-combined',
        'receipt' => 'fa-receipt',
        'id' => 'fa-id-card',
        'clock' => 'fa-clock',
        'exit' => 'fa-right-from-bracket',
        'wallet' => 'fa-wallet',
        'headset' => 'fa-headset',
        'folder' => 'fa-folder',
        'shield' => 'fa-shield-halved',
        'sliders' => 'fa-sliders',
        'bank' => 'fa-building-columns',
        'link' => 'fa-link',
        'key' => 'fa-key',
        'alert' => 'fa-triangle-exclamation',
        'home' => 'fa-house',
        'globe' => 'fa-globe',
        'phone' => 'fa-mobile-screen',
        'eye' => 'fa-eye',
        'gear' => 'fa-gear',
        'settings' => 'fa-gear',
        'upload' => 'fa-upload',
        'download' => 'fa-download',
        'plus' => 'fa-plus',
        'chevR' => 'fa-chevron-right',
    ];

    public static function classFor(?string $icon): string
    {
        return self::MAP[$icon ?? ''] ?? 'fa-circle';
    }
}
