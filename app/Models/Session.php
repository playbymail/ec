<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of the `sessions` table written by the database session driver.
 *
 * @property string $id
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int $last_activity
 * @property-read User|null $user
 */
class Session extends Model
{
    /** @use HasFactory<SessionFactory> */
    use HasFactory;

    /**
     * The browsers recognised in a user agent string, most specific first.
     *
     * @var array<string, string>
     */
    private const BROWSERS = [
        'Edg' => 'Edge',
        'OPR' => 'Opera',
        'Firefox' => 'Firefox',
        'Chrome' => 'Chrome',
        'Safari' => 'Safari',
    ];

    /**
     * The platforms recognised in a user agent string, most specific first.
     *
     * @var array<string, string>
     */
    private const PLATFORMS = [
        'iPhone' => 'iOS',
        'iPad' => 'iPadOS',
        'Android' => 'Android',
        'Macintosh' => 'macOS',
        'Windows' => 'Windows',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    protected $table = 'sessions';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * Get the user the session was authenticated as.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the public identifier for the session.
     *
     * The raw primary key is the session identifier itself: anything holding it
     * can impersonate the session, so it never leaves the server. Screens and
     * routes address a session by this digest instead.
     */
    public function digest(): string
    {
        return hash('sha256', $this->id);
    }

    /**
     * Find the session matching a public digest.
     */
    public static function findByDigest(string $digest): ?self
    {
        return static::query()
            ->get(['id', 'user_id'])
            ->first(fn (self $session): bool => hash_equals($session->digest(), $digest));
    }

    /**
     * Get the moment the session was last used.
     */
    public function lastActiveAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($this->last_activity);
    }

    /**
     * Get the browser the session was started from.
     */
    public function browser(): string
    {
        return $this->matchUserAgent(self::BROWSERS);
    }

    /**
     * Get the operating system the session was started from.
     */
    public function platform(): string
    {
        return $this->matchUserAgent(self::PLATFORMS);
    }

    /**
     * Get the first label whose needle appears in the user agent string.
     *
     * @param  array<string, string>  $labels
     */
    private function matchUserAgent(array $labels): string
    {
        foreach ($labels as $needle => $label) {
            if (str_contains((string) $this->user_agent, $needle)) {
                return $label;
            }
        }

        return 'Unknown';
    }
}
