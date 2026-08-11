import { Form, Head, router } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import ImpersonationController from '@/actions/App/Http/Controllers/ImpersonationController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/admin/users';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    email_verified: boolean;
    two_factor_enabled: boolean;
    sessions_count: number;
    created_at: string | null;
    is_self: boolean;
    can_impersonate: boolean;
};

type Role = {
    value: string;
    label: string;
};

type Props = {
    users: ManagedUser[];
    roles: Role[];
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

export default function Users({ users, roles }: Props) {
    function changeRole(user: ManagedUser, role: string): void {
        if (role === user.role) {
            return;
        }

        router.put(
            UserController.update.url(user.id),
            { role },
            { preserveScroll: true },
        );
    }

    return (
        <div className="px-4 py-6">
            <Head title="Users" />

            <Heading
                title="Users"
                description="Every account on the application. Change a role to grant or withdraw administrator access."
            />

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-left text-sm">
                    <thead className="border-b bg-muted/50 text-muted-foreground">
                        <tr>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Name
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Email
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Role
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Security
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Sessions
                            </th>
                            <th scope="col" className="px-4 py-3 font-medium">
                                Joined
                            </th>
                            <th scope="col" className="px-4 py-3">
                                <span className="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.map((user) => (
                            <tr
                                key={user.id}
                                className="border-b last:border-0"
                            >
                                <td className="px-4 py-3 font-medium">
                                    {user.name}
                                    {user.is_self && (
                                        <span className="ml-2 text-muted-foreground">
                                            (you)
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    {user.email}
                                </td>
                                <td className="px-4 py-3">
                                    {user.is_self ? (
                                        <span className="text-muted-foreground">
                                            {user.role_label}
                                        </span>
                                    ) : (
                                        <Select
                                            value={user.role}
                                            onValueChange={(role) =>
                                                changeRole(user, role)
                                            }
                                        >
                                            <SelectTrigger
                                                className="w-40"
                                                aria-label={`Role for ${user.name}`}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {roles.map((role) => (
                                                    <SelectItem
                                                        key={role.value}
                                                        value={role.value}
                                                    >
                                                        {role.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-wrap gap-1">
                                        {!user.email_verified && (
                                            <Badge variant="outline">
                                                unverified
                                            </Badge>
                                        )}
                                        {user.two_factor_enabled && (
                                            <Badge variant="secondary">
                                                2FA
                                            </Badge>
                                        )}
                                    </div>
                                </td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    {user.sessions_count}
                                </td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    {formatDate(user.created_at)}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-1">
                                        {user.can_impersonate && (
                                            <Form
                                                {...ImpersonationController.store.form(
                                                    user.id,
                                                )}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="ghost"
                                                        size="sm"
                                                        disabled={processing}
                                                        data-test={`impersonate-user-${user.id}-button`}
                                                    >
                                                        Impersonate
                                                    </Button>
                                                )}
                                            </Form>
                                        )}

                                        {!user.is_self && (
                                            <Dialog>
                                                <DialogTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test={`delete-user-${user.id}-button`}
                                                    >
                                                        Delete
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogTitle>
                                                        Delete {user.name}?
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        Their account, passkeys
                                                        and sessions are deleted
                                                        permanently. They can
                                                        only return through a
                                                        new invitation.
                                                    </DialogDescription>

                                                    <Form
                                                        {...UserController.destroy.form(
                                                            user.id,
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
                                                                    data-test={`confirm-delete-user-${user.id}-button`}
                                                                >
                                                                    Delete user
                                                                </Button>
                                                            </DialogFooter>
                                                        )}
                                                    </Form>
                                                </DialogContent>
                                            </Dialog>
                                        )}
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

Users.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: index(),
        },
    ],
};
