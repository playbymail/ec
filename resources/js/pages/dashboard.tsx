import { Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Toggle } from '@/components/ui/toggle';
import { dashboard } from '@/routes';

type GameStatus = 'setup' | 'active' | 'paused' | 'completed' | 'archived';

type SeatedGame = {
    id: number;
    name: string;
    short_name: string;
    status: GameStatus;
    status_label: string;
    is_archived: boolean;
};

type Props = {
    gamemasterGames: SeatedGame[];
    playerGames: SeatedGame[];
};

const statusVariant: Record<GameStatus, 'default' | 'secondary' | 'outline'> = {
    setup: 'outline',
    active: 'default',
    paused: 'secondary',
    completed: 'secondary',
    archived: 'outline',
};

function GameSection({
    title,
    description,
    games,
    testPrefix,
}: {
    title: string;
    description: string;
    games: SeatedGame[];
    testPrefix: string;
}) {
    const [showArchived, setShowArchived] = useState(false);

    const archivedCount = games.filter((game) => game.is_archived).length;
    const visibleGames = showArchived
        ? games
        : games.filter((game) => !game.is_archived);

    return (
        <section data-test={`${testPrefix}-section`}>
            <div className="mb-4 flex flex-wrap items-start justify-between gap-2">
                <Heading
                    title={title}
                    description={description}
                    variant="small"
                />

                {archivedCount > 0 && (
                    <Toggle
                        variant="outline"
                        size="sm"
                        pressed={showArchived}
                        onPressedChange={setShowArchived}
                        data-test={`${testPrefix}-archived-toggle`}
                    >
                        {showArchived ? 'Hide' : 'Show'} archived (
                        {archivedCount})
                    </Toggle>
                )}
            </div>

            <ul className="divide-y rounded-lg border">
                {visibleGames.length === 0 && (
                    <li className="px-4 py-6 text-center text-sm text-muted-foreground">
                        Every game here is archived. Show them to see the list.
                    </li>
                )}

                {visibleGames.map((game) => (
                    <li
                        key={game.id}
                        className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3"
                        data-test={`${testPrefix}-game-${game.id}`}
                    >
                        <span className="font-mono text-sm text-muted-foreground">
                            {game.short_name}
                        </span>
                        <span className="flex-1 text-sm font-medium">
                            {game.name}
                        </span>
                        <Badge variant={statusVariant[game.status]}>
                            {game.status_label}
                        </Badge>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Dashboard({ gamemasterGames, playerGames }: Props) {
    const hasNoGames = gamemasterGames.length === 0 && playerGames.length === 0;

    return (
        <div className="flex h-full flex-1 flex-col gap-8 rounded-xl p-4">
            <Head title="Dashboard" />

            <h1 className="sr-only">Dashboard</h1>

            <Heading
                title="Your games"
                description="The games you hold a seat in, and the role you hold in each of them."
            />

            {hasNoGames && (
                <div
                    className="rounded-lg border border-border p-8 text-center"
                    data-test="no-games-blurb"
                >
                    <p className="font-medium">You are not in any games yet</p>
                    <p className="mx-auto mt-1 max-w-prose text-sm text-muted-foreground">
                        A seat at a game is given to you by an administrator,
                        and it is what puts a game on this page. Once you have
                        one, the games you run and the games you play are listed
                        here separately.
                    </p>
                </div>
            )}

            {gamemasterGames.length > 0 && (
                <GameSection
                    title="Running"
                    description="Games you are the gamemaster of."
                    games={gamemasterGames}
                    testPrefix="gamemaster"
                />
            )}

            {playerGames.length > 0 && (
                <GameSection
                    title="Playing"
                    description="Games you hold a player's seat in."
                    games={playerGames}
                    testPrefix="player"
                />
            )}
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
