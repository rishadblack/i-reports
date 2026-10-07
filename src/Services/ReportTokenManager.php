<?php

namespace Rishadblack\IReports\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * Issues and resolves the short-lived tokens that carry a report request through the iframe URL.
 *
 * Tokens are either stored in the cache ("c:" prefix, nothing sensitive in the URL) or encrypted
 * with the app key ("e:" prefix). Every token records the user that created it; it can only be
 * resolved by that same user while `token_bind_user` is enabled.
 */
class ReportTokenManager
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function store(array $data, ?int $ttlMinutes = null): string
    {
        $ttlMinutes = $ttlMinutes ?? (int) config('i-reports.token_ttl', 10);
        $data['expires_at'] = now()->addMinutes($ttlMinutes)->toIsoString();
        $data['user_id'] = Auth::id();

        if (config('i-reports.use_cache_token')) {
            $token = Str::random(40);
            Cache::put(self::cacheKey($token), $data, now()->addMinutes($ttlMinutes));

            return "c:{$token}";
        }

        return 'e:'.base64_encode(Crypt::encryptString((string) json_encode($data)));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resolve(string $token): ?array
    {
        try {
            if (Str::startsWith($token, 'c:')) {
                $data = Cache::get(self::cacheKey(Str::after($token, 'c:')));
            } elseif (Str::startsWith($token, 'e:')) {
                $decoded = base64_decode(Str::after($token, 'e:'), true);

                if ($decoded === false) {
                    return null;
                }

                $data = json_decode(Crypt::decryptString($decoded), true);
            } else {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        if (! is_array($data) || ! isset($data['expires_at'])) {
            return null;
        }

        if (now()->gt(Carbon::parse($data['expires_at']))) {
            return null;
        }

        if (config('i-reports.token_bind_user', true) && array_key_exists('user_id', $data)) {
            if ($data['user_id'] !== null && (string) $data['user_id'] !== (string) Auth::id()) {
                return null;
            }
        }

        unset($data['expires_at'], $data['user_id']);

        return $data;
    }

    /**
     * Remove a cache-based token so it can no longer be used.
     */
    public static function forget(string $token): void
    {
        if (Str::startsWith($token, 'c:')) {
            Cache::forget(self::cacheKey(Str::after($token, 'c:')));
        }
    }

    protected static function cacheKey(string $token): string
    {
        return 'i-reports:token:'.$token;
    }
}
