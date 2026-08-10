import { Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { login } from '@/routes';

type Reason = 'invalid' | 'accepted' | 'expired';

type Props = {
    reason: Reason;
};

const messages: Record<Reason, string> = {
    invalid:
        'This invitation link is not valid. Check that you copied the whole link from your email.',
    accepted:
        'This invitation has already been used. If the account is yours, log in instead.',
    expired:
        'This invitation has expired. Ask whoever invited you to send a new one.',
};

export default function InvitationInvalid({ reason }: Props) {
    return (
        <>
            <Head title="Invitation unavailable" />

            <div className="flex flex-col gap-6 text-center text-sm text-muted-foreground">
                <p>{messages[reason]}</p>

                <p>
                    <TextLink href={login()}>Go to log in</TextLink>
                </p>
            </div>
        </>
    );
}

InvitationInvalid.layout = {
    title: 'Invitation unavailable',
    description: 'This invitation link can no longer be used',
};
