import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, docs, home, login } from '@/routes';

/**
 * Chrome for the signed-out surface: the landing page and the documentation
 * placeholder. Pages resolved to this layout must not paint a page frame of
 * their own — this owns the background, the width and the vertical rhythm.
 */
export default function PublicLayout({ children }: PropsWithChildren) {
    const { name, auth } = usePage().props;

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <header className="sticky top-0 z-10 border-b border-border/60 bg-background/85 backdrop-blur">
                <div className="mx-auto flex h-16 w-full max-w-5xl items-center justify-between gap-4 px-6">
                    <Link
                        href={home()}
                        className="flex items-center gap-2.5 transition-opacity hover:opacity-80"
                    >
                        <AppLogoIcon className="size-6 fill-current text-foreground" />
                        <span className="text-sm font-semibold tracking-tight">
                            {name}
                        </span>
                    </Link>

                    <nav className="flex items-center gap-1" aria-label="Main">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={docs()}>Docs</Link>
                        </Button>

                        {auth.user ? (
                            <Button size="sm" asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <Button size="sm" asChild>
                                <Link href={login()}>Log in</Link>
                            </Button>
                        )}
                    </nav>
                </div>
            </header>

            <main className="mx-auto w-full max-w-5xl flex-1 px-6 py-16 sm:py-20">
                {children}
            </main>

            <footer className="border-t border-border/60">
                <div className="mx-auto flex w-full max-w-5xl flex-col gap-2 px-6 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                    <p>{name} — access is by invitation only.</p>
                    <Link
                        href={docs()}
                        className="underline-offset-4 transition-colors hover:text-foreground hover:underline"
                    >
                        Documentation
                    </Link>
                </div>
            </footer>
        </div>
    );
}
