import { Form, usePage } from '@inertiajs/react';
import { VenetianMask } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/impersonate';
import type { Auth, Impersonation } from '@/types';

type PageProps = {
    auth: Auth;
    impersonation: Impersonation | null;
};

export function ImpersonationBanner() {
    const { auth, impersonation } = usePage<PageProps>().props;

    if (!impersonation) {
        return null;
    }

    return (
        <div
            className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-amber-500 px-4 py-2 text-sm text-amber-950"
            data-test="impersonation-banner"
        >
            <VenetianMask className="size-4 shrink-0" />

            <p>
                You are signed in as{' '}
                <span className="font-medium">{auth.user?.name}</span>.
                Impersonation started by {impersonation.administrator.name}.
            </p>

            <Form {...destroy.form()} options={{ preserveScroll: false }}>
                {({ processing }) => (
                    <Button
                        type="submit"
                        size="sm"
                        variant="secondary"
                        disabled={processing}
                        data-test="stop-impersonating-button"
                    >
                        Stop impersonating
                    </Button>
                )}
            </Form>
        </div>
    );
}
