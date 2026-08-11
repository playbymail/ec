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
        <div className="px-4 py-6">
            <Head title="Dashboard" />

            <Heading
                title="Your games"
                description="The games you hold a seat in."
            />

            {hasNoGames && (
                <div
                    className="rounded-lg border border-dashed px-6 py-10 text-center"
                    data-test="no-games-blurb"
                >
                    <p className="font-medium">You are not in any games yet.</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        A gamemaster seats you in a game when one is ready for
                        you. It will show up here as soon as they do.
                    </p>
                </div>
            )}

            <div className="flex flex-col gap-8">
                {gamemasterGames.length > 0 && (
                    <GameSection
                        title="Running as gamemaster"
                        description="Games you hold a gamemaster seat in."
                        games={gamemasterGames}
                        testPrefix="gamemaster"
                    />
                )}

                {playerGames.length > 0 && (
                    <GameSection
                        title="Playing"
                        description="Games you hold a player seat in."
                        games={playerGames}
                        testPrefix="player"
                    />
                )}
            </div>
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
