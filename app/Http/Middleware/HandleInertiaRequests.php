<?php

namespace App\Http\Middleware;

use App\Actions\Impersonation\Impersonation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly Impersonation $impersonation) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'impersonation' => fn (): ?array => $this->impersonationState($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Describe the impersonation the session is in the middle of, if any.
     *
     * The banner this feeds is the only way out of an impersonation, so an
     * administrator who has since been deleted or demoted still gets a nameless
     * entry rather than hiding it and stranding the session.
     *
     * @return array{administrator: array{name: string}}|null
     */
    private function impersonationState(Request $request): ?array
    {
        if (! $this->impersonation->isActive($request)) {
            return null;
        }

        $administrator = $this->impersonation->impersonator($request);

        return [
            'administrator' => [
                'name' => $administrator === null ? __('an administrator') : $administrator->name,
            ],
        ];
    }
}
