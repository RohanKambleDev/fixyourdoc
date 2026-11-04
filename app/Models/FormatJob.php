<?php

namespace App\Models;

use App\Enums\FormatJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormatJob extends Model
{
    protected $fillable = [
        'user_id',
        'original_name',
        'input_path',
        'output_path',
        'spec',
        'force_runs',
        'status',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spec' => 'array',
            'force_runs' => 'boolean',
            'status' => FormatJobStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
