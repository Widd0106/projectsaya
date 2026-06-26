<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Character extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'gender',
        'greeting',
        'avatar_path',
        'short_description',
        'long_description',
        'example_dialogues',
    ];

    protected function casts(): array
    {
        return [
            'example_dialogues' => 'array',
        ];
    }

    // Selalu ikut dikirim ke frontend supaya Dashboard.vue & komponen lain
    // tidak perlu membangun URL avatar secara manual.
    protected $appends = ['avatar_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }
}
