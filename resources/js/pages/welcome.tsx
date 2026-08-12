import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Layers, Users, Workflow } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard, docs, login } from '@/routes';

type Capability = {
    title: string;
    description: string;
    icon: LucideIcon;
};

const capabilities: Capability[] = [
    {
        title: 'Game metadata',
        description:
            'Every game in play, with its name, short name and lifecycle state recorded in one place instead of scattered across turn reports and mailing lists.',
        icon: Layers,
    },
    {
        title: 'The seat roster',
        description:
            'Who holds which seat, in which role, in which game. Seats are retired rather than deleted, so a hand-over mid-campaign leaves the record intact.',
        icon: Users,
    },
    {
        title: 'A clean engine boundary',
        description:
            'Turn processing, order resolution and map rendering stay in the game engine. This application answers who and what — never what happened.',
        icon: Workflow,
    },
];

export default function Welcome() {
    const { name, auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />

            <section className="max-w-3xl">
                <span className="inline-flex items-center rounded-full border border-border bg-muted px-3 py-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Invite only
                </span>

                <h1 className="mt-6 text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                    Explore, expand, exploit, exterminate.
                </h1>

                <p className="mt-5 text-lg text-muted-foreground">
                    {name} is a 4X turn-based strategy game where players battle
                    for control of the cluster. Inspired by the original 1978
                    Empyrean Challenge rulebook, it brings the classic
                    play-by-mail space strategy experience to the browser.
                </p>

                <div className="mt-8 flex flex-wrap items-center gap-3">
                    {auth.user ? (
                        <Button asChild>
                            <Link href={dashboard()}>Go to your dashboard</Link>
                        </Button>
                    ) : (
                        <Button asChild>
                            <Link href={login()}>Log in</Link>
                        </Button>
                    )}

                    <Button variant="outline" asChild>
                        <Link href={docs()}>Read the docs</Link>
                    </Button>
                </div>

                {!auth.user && (
                    <p className="mt-4 text-sm text-muted-foreground">
                        Accounts come from invitations — there is no public
                        sign-up. If you have been invited, the invitation
                        carries its own link.
                    </p>
                )}
            </section>

            <section className="mt-16 grid gap-4 sm:mt-20 md:grid-cols-3">
                {capabilities.map((capability) => (
                    <Card key={capability.title}>
                        <CardHeader className="gap-3">
                            <capability.icon className="size-5 shrink-0 text-muted-foreground" />
                            <CardTitle>{capability.title}</CardTitle>
                            <CardDescription className="leading-relaxed">
                                {capability.description}
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ))}
            </section>
        </>
    );
}
