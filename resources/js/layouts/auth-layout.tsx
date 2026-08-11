import { ImpersonationBanner } from '@/components/impersonation-banner';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <>
            {/* An impersonated account may be unverified, which parks it on an
                auth page — the way back has to be reachable from here too. */}
            <ImpersonationBanner />

            <AuthLayoutTemplate title={title} description={description}>
                {children}
            </AuthLayoutTemplate>
        </>
    );
}
