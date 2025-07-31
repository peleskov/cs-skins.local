<?php

namespace App\Services\Steam;

use App\Models\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SessionCache
{
    private const CACHE_PREFIX = 'steam_session:';
    private const DEFAULT_TTL = 180; // 3 минуты

    public function set(int $clientId, array $sessionData, int $ttl = self::DEFAULT_TTL): bool
    {
        $cacheKey = $this->getCacheKey($clientId);
        
        $data = [
            'session' => $sessionData,
            'client_id' => $clientId,
            'updated_at' => now()->toISOString(),
            'expires_at' => now()->addSeconds($ttl)->toISOString()
        ];
        
        $success = Cache::put($cacheKey, $data, $ttl);
        
        if ($success) {
            Log::info('Steam session cached', [
                'client_id' => $clientId,
                'expires_at' => $data['expires_at']
            ]);
        }
        
        return $success;
    }

    public function get(int $clientId): ?array
    {
        $cacheKey = $this->getCacheKey($clientId);
        $data = Cache::get($cacheKey);
        
        if (!$data) {
            return null;
        }
        
        if (!isset($data['session'])) {
            Cache::forget($cacheKey);
            return null;
        }
        
        return $data['session'];
    }

    public function has(int $clientId): bool
    {
        return Cache::has($this->getCacheKey($clientId));
    }

    public function forget(int $clientId): bool
    {
        return Cache::forget($this->getCacheKey($clientId));
    }

    public function getSessionInfo(int $clientId): ?array
    {
        $cacheKey = $this->getCacheKey($clientId);
        return Cache::get($cacheKey);
    }

    public function isExpired(int $clientId): bool
    {
        $info = $this->getSessionInfo($clientId);
        
        if (!$info) {
            return true;
        }
        
        $expiresAt = new \DateTime($info['expires_at']);
        return $expiresAt <= now();
    }

    public function getExpiresInSeconds(int $clientId): int
    {
        $info = $this->getSessionInfo($clientId);
        
        if (!$info) {
            return 0;
        }
        
        $expiresAt = new \DateTime($info['expires_at']);
        $diff = $expiresAt->getTimestamp() - now()->getTimestamp();
        
        return max(0, $diff);
    }

    public function getAllActiveSessions(): array
    {
        $pattern = $this->getCacheKey('*');
        $keys = [];
        
        // Для Redis
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            $redis = Cache::getStore()->getRedis();
            $keys = $redis->keys($pattern);
        }
        
        $sessions = [];
        foreach ($keys as $key) {
            if ($data = Cache::get($key)) {
                $sessions[] = $data;
            }
        }
        
        return $sessions;
    }

    private function getCacheKey(int|string $clientId): string
    {
        return self::CACHE_PREFIX . $clientId;
    }
}