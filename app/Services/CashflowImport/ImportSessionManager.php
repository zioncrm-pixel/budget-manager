<?php

namespace App\Services\CashflowImport;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportSessionManager
{
    private const DISK = 'local';
    private const BASE_PATH = 'cashflow_imports';
    private const TTL_MINUTES = 120;

    public function create(int $userId, array $payload): array
    {
        $sessionId = (string) Str::uuid();

        $record = [
            'id' => $sessionId,
            'user_id' => $userId,
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->toIso8601String(),
            'payload' => $payload,
        ];

        Storage::disk(self::DISK)->put(
            $this->buildPath($userId, $sessionId),
            json_encode($record, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $record;
    }

    public function get(int $userId, string $sessionId): ?array
    {
        $path = $this->buildPath($userId, $sessionId);

        if (!Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $content = Storage::disk(self::DISK)->get($path);
        $record = json_decode($content, true);

        if (!$record) {
            return null;
        }

        if (!empty($record['expires_at']) && now()->greaterThan($record['expires_at'])) {
            Storage::disk(self::DISK)->delete($path);
            return null;
        }

        return $record;
    }

    public function update(int $userId, string $sessionId, callable $mutator): ?array
    {
        $record = $this->get($userId, $sessionId);

        if (!$record) {
            return null;
        }

        $record = $mutator($record) ?? $record;
        $record['updated_at'] = now()->toIso8601String();

        Storage::disk(self::DISK)->put(
            $this->buildPath($userId, $sessionId),
            json_encode($record, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $record;
    }

    public function delete(int $userId, string $sessionId): void
    {
        Storage::disk(self::DISK)->delete($this->buildPath($userId, $sessionId));
    }

    private function buildPath(int $userId, string $sessionId): string
    {
        return sprintf('%s/%d/%s.json', self::BASE_PATH, $userId, $sessionId);
    }
}
