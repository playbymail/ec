import { useSyncExternalStore } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const listeners = new Set<() => void>();
let currentAppearance: Appearance = 'system';

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getCookie = (name: string): string | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(
        new RegExp(`(?:^|;\\s*)${name}=([^;]*)`),
    );

    return match ? decodeURIComponent(match[1]) : null;
};

const isAppearance = (value: string | null): value is Appearance => {
    return value === 'light' || value === 'dark' || value === 'system';
};

/**
 * The `appearance` cookie is the single source of truth, and deliberately the only one.
 *
 * It is the value `resources/views/app.blade.php` resolves the first paint from, so reading it
 * back here is what guarantees hydration re-applies the theme already on screen instead of
 * flipping it. This previously kept the choice in localStorage as well and read that first, which
 * cannot stay in step with a cookie that has its own expiry and its own "clear cookies" button:
 * whichever store outlived the other won, and the loser was what the server had already painted.
 * One store the server can see is worth more than two that can disagree.
 */
const getStoredAppearance = (): Appearance => {
    const cookie = getCookie('appearance');

    return isAppearance(cookie) ? cookie : 'system';
};

const isDarkMode = (appearance: Appearance): boolean => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

const applyTheme = (appearance: Appearance): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const handleSystemThemeChange = (): void => applyTheme(currentAppearance);

export function updateAppearance(mode: Appearance): void {
    currentAppearance = mode;

    setCookie('appearance', mode);
    applyTheme(mode);
    notify();
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    /**
     * Writing the stored value straight back refreshes the cookie's expiry on every visit, so an
     * active user's choice never lapses, and re-applies the theme the server has already painted.
     */
    updateAppearance(getStoredAppearance());

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance(): UseAppearanceReturn {
    const appearance: Appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'system',
    );

    const resolvedAppearance: ResolvedAppearance = isDarkMode(appearance)
        ? 'dark'
        : 'light';

    return { appearance, resolvedAppearance, updateAppearance } as const;
}
