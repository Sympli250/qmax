<?php
/**
 * Theme helper functions.
 * Provides dynamic asset paths for themes.
 */

function getThemeName(): string {
    return getConfig('theme', 'default');
}

function getThemeAssetUrl(string $asset): string {
    return 'themes/' . getThemeName() . '/' . ltrim($asset, '/');
}

function getThemeStylesheetUrl(): string {
    return getThemeAssetUrl('style.css');
}

