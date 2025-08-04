<?php
namespace Rishadblack\IReports\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ReportTokenManager
{
    /**
     * Store report data using cache (if enabled) or fallback to encryption.
     */
    public static function store(array $data, int $ttlMinutes = 10): string
    {
        $data['expires_at'] = now()->addMinutes($ttlMinutes)->toIsoString();

        // Check config
        if (Config::get('i-reports.use_cache_token') && self::supportsTags()) {
            $token = Str::random(32);
            Cache::put("ireport_token:$token", $data, now()->addMinutes($ttlMinutes));
            return "c:$token"; // c: = cache-based token
        }

        // Else: encrypt
        $json = json_encode($data);
        $encrypted = Crypt::encryptString($json);
        return "e:" . base64_encode($encrypted); // e: = encrypted token
    }

    /**
     * Resolve the token either from cache or encrypted string.
     */
    public static function resolve(string $token): ?array
    {
        try {
            if (Str::startsWith($token, 'c:')) {
                $plainToken = Str::after($token, 'c:');
                $data = Cache::get("ireport_token:$plainToken");
            } elseif (Str::startsWith($token, 'e:')) {
                $decoded = base64_decode(Str::after($token, 'e:'));
                $json = Crypt::decryptString($decoded);
                $data = json_decode($json, true);
            } else {
                return null;
            }

            if (! isset($data['expires_at']) || now()->gt(Carbon::parse($data['expires_at']))) {
                return null; // Expired
            }

            return $data;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check if current cache driver supports persistent storage.
     */
    protected static function supportsTags(): bool
    {
        $driver = Cache::getStore();

        return method_exists($driver, 'tags') &&
        in_array(config('cache.default'), ['redis', 'memcached']);
    }
}
