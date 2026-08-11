import { Form, Head, Link } from '@inertiajs/react';
import GameController from '@/actions/App/Http/Controllers/Admin/GameController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, show } from '@/routes/admin/games';

type GameStatus = 'setup' | 'active' | 'paused' | 'completed' | 'archived';

type ManagedGame = {
    id: number;
    name: string;
    short_name: string;
    status: GameStatus;
    status_label: string;
    seats_count: number;
    active_seats_count: number;
    created_at: string | null;
};

type Props = {
    games: ManagedGame[];
};

const statusVariant: Record<GameStatus, 'default' | 'secondary' | 'outline'> = {
    setup: 'outline',
    active: 'default',
    paused: 'secondary',
    completed: 'secondary',
    archived: 'outline',
};

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function Games({ games }: Props) {
    return (
        <div className="px-4 py-6">
            <Head title="Games" />

            <Heading
                title="Games"
                description="Every game on the application. A new game starts in setup so you can seat accounts before turns run."
            />

            <Form
                {...GameController.store.form()}
                resetOnSuccess={['name', 'short_name']}
                disableWhileProcessing
                className="mb-8 max-w-2xl"
            >
                {({ processing, errors }) => (
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                autoComplete="off"
                                placeholder="The Epimethean Challenge"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2 sm:w-40">
                            <Label htmlFor="short_name">Short name</Label>
                            <Input
                                id="short_name"
                                name="short_name"
                                required
                                autoComplete="off"
                                maxLength={16}
                                placeholder="EC01"
                            />
                            <InputError message={errors.short_name} />
                        </div>

                        <Button
                            type="submit"
                            className="sm:mt-6"
                            data-test="create-game-button"
                        >
                            {processing && <Spinner />}
                            Create game
                        </Button>
                    </div>
                )}
            </Form>

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-left text-sm">
                    <thead className="border-b bg-muted/50 text-muted-foreground">
                        <tr>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Name
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Short name
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Status
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Seats
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Created
                            </th>
                            <th scope="col" className="px-4 py-3">
                                <span className="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {games.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-6 text-center text-muted-foreground"
                                >
                                    No games yet.
                                </td>
                            </tr>
                        )}

                        {games.map((game) => (
                            <tr
                                key={game.id}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-3 font-medium">
                                    <Link
                                        href={show(game.id)}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {game.name}
                                    </Link>
                                </td>
                                <td className="px-4 py-3 font-mono text-muted-foreground">
                                    {game.short_name}
                                </td>
                                <td className="px-4 py-3">
                                    <Badge variant={statusVariant[game.status]}>
                                        {game.status_label}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    {game.active_seats_count} active
                                    {game.seats_count !==
                                        game.active_seats_count &&
                                        ` of ${game.seats_count}`}
                                </td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    {formatDate(game.created_at)}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={show(game.id)}>
                                                Manage
                                            </Link>
                                        </Button>

                                        <Dialog>
                                            <DialogTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    data-test={`delete-game-${game.id}-button`}
                                                >
                                                    Delete
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <DialogTitle>
                                                    Delete {game.name}?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    The game and all{' '}
                                                    {game.seats_count} of its
                                                    seats are deleted
                                                    permanently. Archive the
                                                    game instead if you only
                                                    want it out of the way.
                                                </DialogDescription>

                                                <Form
                                                    {...GameController.destroy.form(
                                                        game.id,
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <DialogFooter className="gap-2">
                                                            <DialogClose
                                                                asChild
                                                            >
                                                                <Button variant="secondary">
                                                                    Cancel
                                                                </Button>
                                                            </DialogClose>

                                                            <Button
                                                                type="submit"
                                                                variant="destructive"
                                                                disabled={
                                                                    processing
                                                                }
                                                                data-test={`confirm-delete-game-${game.id}-button`}
                                                            >
                                                                Delete game
                                                            </Button>
                                                        </DialogFooter>
                                                    )}
                                                </Form>
                                            </DialogContent>
                                        </Dialog>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

Games.layout = {
    breadcrumbs: [
        {
            title: 'Games',
            href: index(),
        },
    ],
};
