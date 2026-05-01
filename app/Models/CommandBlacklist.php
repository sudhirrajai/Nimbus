<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommandBlacklist extends Model
{
    protected $table = 'command_blacklist';

    protected $fillable = [
        'pattern',
        'type',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if a command matches this blacklist pattern.
     */
    public function matches(string $command): bool
    {
        if (!$this->is_active) {
            return false;
        }

        return match ($this->type) {
            'exact' => strtolower(trim($command)) === strtolower(trim($this->pattern)),
            'contains' => str_contains(strtolower($command), strtolower($this->pattern)),
            'regex' => (bool) preg_match($this->pattern, $command),
            default => false,
        };
    }
}
