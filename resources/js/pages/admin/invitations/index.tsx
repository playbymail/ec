import { Form, Head } from '@inertiajs/react';
import InvitationController from '@/actions/App/Http/Controllers/Admin/InvitationController';
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
import { index } from '@/routes/admin/invitations';

type InvitationStatus = 'pending' | 'accepted' | 'expired';

type Invitation = {
    id: number;
    email: string;
    role: string;
    role_label: string;
    status: InvitationStatus;
    invited_by: string | null;
    expires_at: string;
    created_at: string | null;
};

type Role = {
    value: string;
    label: string;
};

type Props = {
    invitations: Invitation[];
    roles: Role[];
};

const statusVariant: Record<
    InvitationStatus,
    'default' | 'secondary' | 'outline'
> = {
    pending: 'default',
    accepted: 'secondary',
    expired: 'outline',
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

export default function Invitations({ invitations, roles }: Props) {
    return (
        <div className="flex h-full flex-1 flex-col gap-8 rounded-xl p-4">
            <Head title="Invitations" />

            <h1 className="sr-only">Invitations</h1>

            <Heading
                title="Invitations"
                description="Accounts are created by invitation only. Invite an email address, and whoever accepts it gets the role you choose here."
            />

            <section className="space-y-4">
                <Heading
                    variant="small"
                    title="Invite someone"
                    description="They are emailed a link that creates their account."
                />

                <Form
                    {...InvitationController.store.form()}
                    resetOnSuccess={['email']}
                    disableWhileProcessing
                    className="max-w-2xl"
                >
                    {({ processing, errors }) => (
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoComplete="off"
                                    placeholder="person@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2 sm:w-48">
                                <Label htmlFor="role">Role</Label>
                                <Select name="role" defaultValue="member">
                                    <SelectTrigger id="role" className="w-full">
                                        <SelectValue placeholder="Role" />
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
                                <InputError message={errors.role} />
                            </div>

                            <Button
                                type="submit"
                                className="sm:mt-6"
                                data-test="send-invitation-button"
                            >
                                {processing && <Spinner />}
                                Send invitation
                            </Button>
                        </div>
                    )}
                </Form>
            </section>

            <section className="space-y-4">
                <Heading
                    variant="small"
                    title="All invitations"
                    description="Accepted and expired invitations are kept, so you can see who was invited and who never arrived."
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground">
                            <tr>
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
                                    Role
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Invited by
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Expires
                                </th>
                                <th scope="col" className="px-4 py-3">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {invitations.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No invitations yet.
                                    </td>
                                </tr>
                            )}

                            {invitations.map((invitation) => (
                                <tr
                                    key={invitation.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {invitation.email}
                                    </td>
                                    <td className="px-4 py-3">
                                        {invitation.role_label}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                statusVariant[invitation.status]
                                            }
                                        >
                                            {invitation.status}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {invitation.invited_by ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatDate(invitation.expires_at)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-2">
                                            {invitation.status !==
                                                'accepted' && (
                                                <Form
                                                    {...InvitationController.update.form(
                                                        invitation.id,
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Resend
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}

                                            <Form
                                                {...InvitationController.destroy.form(
                                                    invitation.id,
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
                                                        Revoke
                                                    </Button>
                                                )}
                                            </Form>
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

Invitations.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminIndex(),
        },
        {
            title: 'Invitations',
            href: index(),
        },
    ],
};
