import { Head, Link } from '@inertiajs/react';
import { home } from '@/routes';

export default function Docs() {
    return (
        <>
            <Head title="Documentation" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:p-8 dark:bg-[#0a0a0a]">
                <main className="w-full max-w-[335px] rounded-lg bg-white p-6 pb-12 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:max-w-xl lg:p-12 dark:bg-[#161615] dark:text-[#EDEDEC] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                    <h1 className="mb-1 text-lg font-medium">Documentation</h1>
                    <p className="mb-6 text-[#706f6c] dark:text-[#A1A09A]">
                        The Epimethean Challenge documentation is still being
                        written. Rules, turn formats, and command references
                        will land here as they are finished.
                    </p>
                    <Link
                        href={home()}
                        className="inline-block rounded-sm border border-black bg-[#1b1b18] px-5 py-1.5 text-sm leading-normal text-white hover:border-black hover:bg-black dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:border-white dark:hover:bg-white"
                    >
                        Back to home
                    </Link>
                </main>
            </div>
        </>
    );
}
