<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $peserta_id
 * @property int $ujian_id
 * @property int $soal_id
 * @property string|null $jawaban_peserta
 * @property bool|null $benar
 * @property-read \App\Models\Peserta $peserta
 * @property-read \App\Models\Soal $soal
 * @property-read \App\Models\Ujian $ujian
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jawaban newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jawaban newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jawaban query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jawaban whereBenar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jawaban whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jawaban whereJawabanPeserta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jawaban wherePesertaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jawaban whereSoalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Jawaban whereUjianId($value)
 * @mixin \Eloquent
 */
class Jawaban extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'jawaban';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'peserta_id',
        'ujian_id',
        'soal_id',
        'jawaban_peserta',
        'benar',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'benar' => 'boolean',
        ];
    }

    /**
     * Get the peserta that owns this jawaban.
     */
    public function peserta(): BelongsTo
    {
        return $this->belongsTo(Peserta::class);
    }

    /**
     * Get the ujian that owns this jawaban.
     */
    public function ujian(): BelongsTo
    {
        return $this->belongsTo(Ujian::class);
    }

    /**
     * Get the soal that owns this jawaban.
     */
    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class);
    }

    /**
     * Check if the answer is correct.
     *
     * @return bool
     */
    public function isCorrect(): bool
    {
        return $this->benar === true;
    }

    /**
     * Check if the answer is wrong.
     *
     * @return bool
     */
    public function isWrong(): bool
    {
        return $this->benar === false;
    }

    /**
     * Check if the answer is not yet graded.
     *
     * @return bool
     */
    public function isNotGraded(): bool
    {
        return $this->benar === null;
    }

    /**
     * Mark the answer as correct.
     *
     * @return void
     */
    public function markAsCorrect(): void
    {
        $this->benar = true;
    }

    /**
     * Mark the answer as wrong.
     *
     * @return void
     */
    public function markAsWrong(): void
    {
        $this->benar = false;
    }

    /**
     * Auto-grade the answer based on the correct answer from soal.
     *
     * @return void
     */
    public function autoGrade(): void
    {
        if ($this->soal && $this->jawaban_peserta) {
            $this->benar = $this->soal->isCorrectAnswer($this->jawaban_peserta);
            $this->save();
        }
    }
}