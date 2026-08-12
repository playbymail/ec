import { Form, Head } from '@inertiajs/react';
import SessionController from '@/actions/App/Http/Controllers/Admin/SessionController';
import Heading from '@/components/heading';
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
import { index as adminIndex } from '@/routes/admin';
import { index } from '@/routes/admin/sessions';

type ActiveSession = {
    digest: string;
    user: {
        id: number | null;
        name: string | null;
        email: string | null;
    };
    ip_address: string | null;
    browser: string;
    platform: string;
    last_active_at: string;
    is_current: boolean;
};

type Props = {
    sessions: ActiveSession[];
};

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function Sessions({ sessions }: Props) {
    const revocable = sessions.filter((session) => !session.is_current);

    return (
        <div className="flex h-full flex-1 flex-col gap-8 rounded-xl p-4">
            <Head title="Sessions" />

            <h1 className="sr-only">Sessions</h1>

            <Heading
                title="Sessions"
                description="Every browser currently signed in. Signing one out ends it immediately — whoever is using it has to log in again."
            />

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-left text-sm">
                    <thead className="border-b bg-muted/50 text-muted-foreground">
                        <tr>
                            <th scope="col" className="px-4 py-3 font-medium">
                                User
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Device
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                IP address
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Last active
                            </th>
                            <th scope="col" className="px-4 py-3">
                                <span className="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {sessions.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Nobody is signed in.
                                </td>
                            </tr>
                        )}

                        {sessions.map((session) => (
                            <tr
                                key={session.digest}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-3">
                                    <div className="font-medium">
                                        {session.user.name ?? 'Unknown'}
                                        {session.is_current && (
                                            <Badge
                                                variant="secondary"
                                                className="ml-2"
                                            >
                                                this browser
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {session.user.email ?? '—'}
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    {session.browser} on {session.platform}
                                </td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    {session.ip_address ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    {formatDateTime(session.last_active_at)}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end">
                                        {!session.is_current && (
                                            <Form
                                                {...SessionController.destroy.form(
                                                    session.digest,
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="ghost"
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        Sign out
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {revocable.length > 0 && (
                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Sign out everybody else"
                        description="Ends every session except the one you are reading this in."
                    />

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button
                                variant="outline"
                                data-test="sign-out-all-button"
                            >
                                Sign out all others
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                Sign out {revocable.length}{' '}
                                {revocable.length === 1
                                    ? 'session'
                                    : 'sessions'}
                                ?
                            </DialogTitle>
                            <DialogDescription>
                                Everyone except this browser is signed out
                                immediately and has to log in again.
                            </DialogDescription>

                            <Form {...SessionController.destroyAll.form()}>
                                {({ processing }) => (
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                Cancel
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                            data-test="confirm-sign-out-all-button"
                                        >
                                            Sign them out
                                        </Button>
                                    </DialogFooter>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </section>
            )}
        </div>
    );
}

Sessions.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminIndex(),
        },
        {
            title: 'Sessions',
            href: index(),
        },
    ],
};
