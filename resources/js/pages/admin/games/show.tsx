import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import GameController from '@/actions/App/Http/Controllers/Admin/GameController';
import GameSeatController from '@/actions/App/Http/Controllers/Admin/GameSeatController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { index as adminIndex } from '@/routes/admin';
import { index } from '@/routes/admin/games';

type Game = {
    id: number;
    name: string;
    short_name: string;
    status: string;
    status_label: string;
    created_at: string | null;
};

type Seat = {
    id: number;
    user_id: number;
    user_name: string;
    user_email: string;
    role: string;
    role_label: string;
    is_active: boolean;
    created_at: string | null;
};

type Option = {
    value: string;
    label: string;
};

type AssignableUser = {
    id: number;
    name: string;
    email: string;
};

type Props = {
    game: Game;
    seats: Seat[];
    statuses: Option[];
    gameRoles: Option[];
    assignableUsers: AssignableUser[];
};

const DEFAULT_GAME_ROLE = 'player';

export default function ShowGame({
    game,
    seats,
    statuses,
    gameRoles,
    assignableUsers,
}: Props) {
    const [seatUserId, setSeatUserId] = useState<string>('');
    const [seatRole, setSeatRole] = useState<string>(DEFAULT_GAME_ROLE);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Administration', href: adminIndex() },
            { title: 'Games', href: index() },
            { title: game.name, href: GameController.show.url(game.id) },
        ],
    });

    function updateSeat(seat: Seat, changes: Partial<Seat>): void {
        router.put(
            GameSeatController.update.url({ game: game.id, seat: seat.id }),
            {
                role: changes.role ?? seat.role,
                is_active: changes.is_active ?? seat.is_active,
            },
            { preserveScroll: true },
        );
    }

    return (
        <div className="flex h-full flex-1 flex-col gap-8 rounded-xl p-4">
            <Head title={game.name} />

            <h1 className="sr-only">{game.name}</h1>

            <Heading
                title={game.name}
                description="The application owns this metadata and the seat roster. Game state belongs to the engine."
            />

            <section className="space-y-4">
                <Heading
                    variant="small"
                    title="Details"
                    description="The short name appears in turn reports and file names, so it is uppercased and limited to letters, numbers and hyphens."
                />

                <Form
                    {...GameController.update.form(game.id)}
                    options={{ preserveScroll: true }}
                    disableWhileProcessing
                    className="max-w-3xl"
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
                                    defaultValue={game.name}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2 sm:w-36">
                                <Label htmlFor="short_name">Short name</Label>
                                <Input
                                    id="short_name"
                                    name="short_name"
                                    required
                                    autoComplete="off"
                                    maxLength={16}
                                    defaultValue={game.short_name}
                                />
                                <InputError message={errors.short_name} />
                            </div>

                            <div className="grid gap-2 sm:w-40">
                                <Label htmlFor="status">Status</Label>
                                <Select
                                    name="status"
                                    defaultValue={game.status}
                                >
                                    <SelectTrigger
                                        id="status"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statuses.map((status) => (
                                            <SelectItem
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.status} />
                            </div>

                            <Button
                                type="submit"
                                className="sm:mt-6"
                                data-test="save-game-button"
                            >
                                {processing && <Spinner />}
                                Save game
                            </Button>
                        </div>
                    )}
                </Form>
            </section>

            <section className="space-y-4">
                <Heading
                    variant="small"
                    title="Add a seat"
                    description="A game role applies to this game only — a gamemaster here is not an administrator anywhere."
                />

                <Form
                    {...GameSeatController.store.form(game.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setSeatUserId('');
                        setSeatRole(DEFAULT_GAME_ROLE);
                    }}
                    disableWhileProcessing
                    className="max-w-3xl"
                >
                    {({ processing, errors }) => (
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="user_id">Account</Label>
                                <Select
                                    name="user_id"
                                    value={seatUserId}
                                    onValueChange={setSeatUserId}
                                    disabled={assignableUsers.length === 0}
                                    required
                                >
                                    <SelectTrigger
                                        id="user_id"
                                        className="w-full"
                                    >
                                        <SelectValue
                                            placeholder={
                                                assignableUsers.length === 0
                                                    ? 'Every account already has a seat'
                                                    : 'Choose an account'
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {assignableUsers.map((user) => (
                                            <SelectItem
                                                key={user.id}
                                                value={String(user.id)}
                                            >
                                                {user.name} ({user.email})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.user_id} />
                            </div>

                            <div className="grid gap-2 sm:w-48">
                                <Label htmlFor="role">Game role</Label>
                                <Select
                                    name="role"
                                    value={seatRole}
                                    onValueChange={setSeatRole}
                                >
                                    <SelectTrigger id="role" className="w-full">
                                        <SelectValue placeholder="Game role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {gameRoles.map((role) => (
                                            <SelectItem
                                                key={role.value}
                                                value={role.value}
                                            >
                                                {role.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.role} />
                            </div>

                            <Button
                                type="submit"
                                className="sm:mt-6"
                                disabled={assignableUsers.length === 0}
                                data-test="add-seat-button"
                            >
                                {processing && <Spinner />}
                                Add seat
                            </Button>
                        </div>
                    )}
                </Form>
            </section>

            <section className="space-y-4">
                <Heading
                    variant="small"
                    title="Roster"
                    description="Each account holds one seat per game. Retire a seat instead of deleting it so the engine's history keeps making sense — reactivate it to bring the account back."
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Account
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Email
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Game role
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Seat
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {seats.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        Nobody is seated in this game yet.
                                    </td>
                                </tr>
                            )}

                            {seats.map((seat) => (
                                <tr
                                    key={seat.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {seat.user_name}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {seat.user_email}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Select
                                            value={seat.role}
                                            onValueChange={(role) => {
                                                if (role !== seat.role) {
                                                    updateSeat(seat, { role });
                                                }
                                            }}
                                        >
                                            <SelectTrigger
                                                className="w-44"
                                                aria-label={`Game role for ${seat.user_name}`}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {gameRoles.map((role) => (
                                                    <SelectItem
                                                        key={role.value}
                                                        value={role.value}
                                                    >
                                                        {role.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                seat.is_active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {seat.is_active
                                                ? 'Active'
                                                : 'Retired'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    updateSeat(seat, {
                                                        is_active:
                                                            !seat.is_active,
                                                    })
                                                }
                                                data-test={`toggle-seat-${seat.id}-button`}
                                            >
                                                {seat.is_active
                                                    ? 'Retire'
                                                    : 'Reactivate'}
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    );
}
