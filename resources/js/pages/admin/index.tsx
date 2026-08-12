import { Head, Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ChevronRight,
    Dices,
    MailPlus,
    MonitorSmartphone,
    Users,
} from 'lucide-react';
import Heading from '@/components/heading';
import { index } from '@/routes/admin';
import { index as gamesIndex } from '@/routes/admin/games';
import { index as invitationsIndex } from '@/routes/admin/invitations';
import { index as sessionsIndex } from '@/routes/admin/sessions';
import { index as usersIndex } from '@/routes/admin/users';

type Counts = {
    invitations: number;
    users: number;
    games: number;
    sessions: number;
};

type Props = {
    counts: Counts;
};

type Area = {
    key: keyof Counts;
    title: string;
    description: string;
    /** What the count on this card is counting, singular and plural. */
    unit: [string, string];
    href: ReturnType<typeof usersIndex>;
    icon: LucideIcon;
};

const areas: Area[] = [
    {
        key: 'invitations',
        title: 'Invitations',
        description:
            'Invite people, choose their role, and revoke links that should no longer work.',
        unit: ['pending invitation', 'pending invitations'],
        href: invitationsIndex(),
        icon: MailPlus,
    },
    {
        key: 'users',
        title: 'Users',
        description:
            'See every account, change the role one holds, and delete accounts that should no longer exist.',
        unit: ['account', 'accounts'],
        href: usersIndex(),
        icon: Users,
    },
    {
        key: 'games',
        title: 'Games',
        description:
            'Create games and manage who sits at each one. A game role applies to that game only.',
        unit: ['unarchived game', 'unarchived games'],
        href: gamesIndex(),
        icon: Dices,
    },
    {
        key: 'sessions',
        title: 'Sessions',
        description:
            'See which browsers are signed in, and sign any of them out.',
        unit: ['signed-in browser', 'signed-in browsers'],
        href: sessionsIndex(),
        icon: MonitorSmartphone,
    },
];

function AreaCard({ area, count }: { area: Area; count: number }) {
    const Icon = area.icon;

    return (
        <Link
            href={area.href}
            className="flex items-center gap-4 rounded-lg border p-4 transition-colors hover:bg-accent"
        >
            <div
                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted"
                aria-hidden="true"
            >
                <Icon className="h-5 w-5 text-muted-foreground" />
            </div>

            <div className="space-y-0.5">
                <p className="font-medium tracking-tight">{area.title}</p>
                <p className="text-sm text-muted-foreground">
                    {area.description}
                </p>
                <p className="text-sm text-muted-foreground">
                    {count} {count === 1 ? area.unit[0] : area.unit[1]}
                </p>
            </div>

            <ChevronRight
                className="ms-auto h-4 w-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
        </Link>
    );
}

export default function AdminIndex({ counts }: Props) {
    return (
        <div className="flex h-full flex-1 flex-col gap-8 rounded-xl p-4">
            <Head title="Administration" />

            <h1 className="sr-only">Administration</h1>

            <Heading
                title="Administration"
                description="Everything here is restricted to administrators. A gamemaster seat grants none of it."
            />

            <nav
                className="grid gap-3 sm:grid-cols-2"
                aria-label="Administration"
            >
                {areas.map((area) => (
                    <AreaCard
                        key={area.key}
                        area={area}
                        count={counts[area.key]}
                    />
                ))}
            </nav>
        </div>
    );
}

AdminIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: index(),
        },
    ],
};
