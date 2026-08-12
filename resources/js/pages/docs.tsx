import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

const plannedSections = [
    'Rules: the game as it is played today',
    'Turn formats: what to send, and what comes back',
    'Command reference: every order, with worked examples',
    'Accepting an invitation and setting up your account',
];

export default function Docs() {
    return (
        <>
            <Head title="Documentation" />

            <section className="max-w-3xl">
                <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                    Documentation
                </h1>

                <p className="mt-4 text-lg text-muted-foreground">
                    Still being written. Rules, turn formats and command
                    references will land here as they are finished.
                </p>

                <h2 className="mt-10 text-sm font-medium tracking-wide uppercase">
                    Planned sections
                </h2>

                <ul className="mt-4 space-y-3 text-muted-foreground">
                    {plannedSections.map((section) => (
                        <li key={section} className="flex gap-3">
                            <span
                                className="mt-2.5 size-1.5 shrink-0 rounded-full bg-border"
                                aria-hidden="true"
                            />
                            <span>{section}</span>
                        </li>
                    ))}
                </ul>

                <div className="mt-10">
                    <Button variant="outline" asChild>
                        <Link href={home()}>Back to the start</Link>
                    </Button>
                </div>
            </section>
        </>
    );
}
