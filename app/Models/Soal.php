<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $ujian_id
 * @property int $nomor_soal
 * @property string|null $tipe_soal
 * @property string|null $deskripsi_soal
 * @property string $pertanyaan
 * @property string|null $media_soal
 * @property string|null $opsi_a
 * @property string|null $opsi_a_media
 * @property string|null $opsi_b
 * @property string|null $opsi_b_media
 * @property string|null $opsi_c
 * @property string|null $opsi_c_media
 * @property string|null $opsi_d
 * @property string|null $opsi_d_media
 * @property string|null $opsi_e
 * @property string|null $opsi_e_media
 * @property string|null $jawaban_benar
 * @property-read array $options
 * @property-read array $options_media
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Jawaban> $jawaban
 * @property-read int|null $jawaban_count
 * @property-read \App\Models\Ujian $ujian
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereDeskripsiSoal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereJawabanBenar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereMediaSoal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereNomorSoal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereOpsiA($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereOpsiAMedia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereOpsiB($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereOpsiBMedia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereOpsiC($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereOpsiCMedia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereOpsiD($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereOpsiDMedia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereOpsiE($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereOpsiEMedia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal wherePertanyaan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereTipeSoal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Soal whereUjianId($value)
 * @mixin \Eloquent
 */
class Soal extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'soal';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ujian_id',
        'nomor_soal',
        'tipe_soal',
        'deskripsi_soal',
        'pertanyaan',
        'media_soal',
        'opsi_a',
        'opsi_a_media',
        'opsi_b',
        'opsi_b_media',
        'opsi_c',
        'opsi_c_media',
        'opsi_d',
        'opsi_d_media',
        'opsi_e',
        'opsi_e_media',
        'jawaban_benar',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nomor_soal' => 'integer',
            'tipe_soal' => 'string',
        ];
    }

    /**
     * Get the ujian that owns this soal.
     */
    public function ujian(): BelongsTo
    {
        return $this->belongsTo(Ujian::class);
    }

    /**
     * Get all jawaban for this soal.
     */
    public function jawaban(): HasMany
    {
        return $this->hasMany(Jawaban::class);
    }

    /**
     * Get all option texts as an array.
     *
     * @return array
     */
    public function getOptionsAttribute(): array
    {
        return [
            'a' => $this->opsi_a,
            'b' => $this->opsi_b,
            'c' => $this->opsi_c,
            'd' => $this->opsi_d,
            'e' => $this->opsi_e,
        ];
    }

    /**
     * Get all option media as an array.
     *
     * @return array
     */
    public function getOptionsMediaAttribute(): array
    {
        return [
            'a' => $this->opsi_a_media,
            'b' => $this->opsi_b_media,
            'c' => $this->opsi_c_media,
            'd' => $this->opsi_d_media,
            'e' => $this->opsi_e_media,
        ];
    }

    /**
     * Check if the given answer is correct.
     *
     * @param string $answer
     * @return bool
     */
    public function isCorrectAnswer(string $answer): bool
    {
        return strtolower($this->jawaban_benar) === strtolower($answer);
    }
}