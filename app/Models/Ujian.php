<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nama_ujian
 * @property string|null $deskripsi
 * @property \Illuminate\Support\Carbon $waktu_mulai_pengerjaan
 * @property \Illuminate\Support\Carbon $waktu_akhir_pengerjaan
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AktivitasPeserta> $aktivitasPeserta
 * @property-read int|null $aktivitas_peserta_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Jawaban> $jawaban
 * @property-read int|null $jawaban_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Peserta> $peserta
 * @property-read int|null $peserta_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Soal> $soal
 * @property-read int|null $soal_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ujian newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ujian newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ujian query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ujian whereDeskripsi($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ujian whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ujian whereNamaUjian($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ujian whereWaktuAkhirPengerjaan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ujian whereWaktuMulaiPengerjaan($value)
 * @mixin \Eloquent
 */
class Ujian extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ujian';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama_ujian',
        'deskripsi',
        'waktu_mulai_pengerjaan',
        'waktu_akhir_pengerjaan',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'waktu_mulai_pengerjaan' => 'datetime',
            'waktu_akhir_pengerjaan' => 'datetime',
        ];
    }

    /**
     * Get all soal for this ujian.
     */
    public function soal(): HasMany
    {
        return $this->hasMany(Soal::class);
    }

    /**
     * Get all aktivitas peserta for this ujian.
     */
    public function aktivitasPeserta(): HasMany
    {
        return $this->hasMany(AktivitasPeserta::class);
    }

    /**
     * Get all jawaban for this ujian.
     */
    public function jawaban(): HasMany
    {
        return $this->hasMany(Jawaban::class);
    }

    /**
     * Get all peserta who have taken this ujian.
     */
    public function peserta()
    {
        return $this->belongsToMany(Peserta::class, 'aktivitas_peserta', 'ujian_id', 'peserta_id')
                    ->withPivot(['status', 'waktu_login', 'waktu_submit'])
                    ->withTimestamps();
    }
}