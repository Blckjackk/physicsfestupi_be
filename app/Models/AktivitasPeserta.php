<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $peserta_id
 * @property int $ujian_id
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $waktu_login
 * @property \Illuminate\Support\Carbon|null $waktu_submit
 * @property-read \App\Models\Peserta $peserta
 * @property-read \App\Models\Ujian $ujian
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AktivitasPeserta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AktivitasPeserta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AktivitasPeserta query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AktivitasPeserta whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AktivitasPeserta wherePesertaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AktivitasPeserta whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AktivitasPeserta whereUjianId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AktivitasPeserta whereWaktuLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AktivitasPeserta whereWaktuSubmit($value)
 * @mixin \Eloquent
 */
class AktivitasPeserta extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'aktivitas_peserta';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'peserta_id',
        'ujian_id',
        'status',
        'waktu_login',
        'waktu_submit'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'waktu_login' => 'datetime',
            'waktu_submit' => 'datetime',
            'status' => 'string',
        ];
    }

    /**
     * Get the peserta that owns this aktivitas.
     */
    public function peserta(): BelongsTo
    {
        return $this->belongsTo(Peserta::class);
    }

    /**
     * Get the ujian that owns this aktivitas.
     */
    public function ujian(): BelongsTo
    {
        return $this->belongsTo(Ujian::class);
    }

    /**
     * Check if peserta has logged in.
     *
     * @return bool
     */
    public function hasLoggedIn(): bool
    {
        return $this->status !== 'belum_login';
    }

    /**
     * Check if peserta is currently working on the exam.
     *
     * @return bool
     */
    public function isWorking(): bool
    {
        return $this->status === 'sedang_mengerjakan';
    }

    /**
     * Check if peserta has submitted the exam.
     *
     * @return bool
     */
    public function hasSubmitted(): bool
    {
        return $this->status === 'sudah_submit';
    }

    /**
     * Get the duration of the exam in minutes.
     *
     * @return int|null
     */
    public function getDurationInMinutes(): ?int
    {
        if (!$this->waktu_login || !$this->waktu_submit) {
            return null;
        }

        return $this->waktu_login->diffInMinutes($this->waktu_submit);
    }
}