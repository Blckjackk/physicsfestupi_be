<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
// use Laravel\Sanctum\HasApiTokens; // Commented out temporarily

/**
 * @property int $id
 * @property string $username
 * @property string $password_hash
 * @property string|null $role
 * @property int|null $nilai_total
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AktivitasPeserta> $aktivitasPeserta
 * @property-read int|null $aktivitas_peserta_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Jawaban> $jawaban
 * @property-read int|null $jawaban_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ujian> $ujian
 * @property-read int|null $ujian_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Peserta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Peserta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Peserta query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Peserta whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Peserta whereNilaiTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Peserta wherePasswordHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Peserta whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Peserta whereUsername($value)
 * @mixin \Eloquent
 */
class Peserta extends Authenticatable
{
    use HasFactory, Notifiable; // Removed HasApiTokens temporarily

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'peserta';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'password_hash',
        'password_plain',
        'role',
        'nilai_total',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => 'string',
            'nilai_total' => 'integer',
        ];
    }

    /**
     * Get the password attribute name for authentication.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Get the username attribute name for authentication.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return 'username';
    }

    /**
     * Get all aktivitas for this peserta.
     */
    public function aktivitasPeserta(): HasMany
    {
        return $this->hasMany(AktivitasPeserta::class);
    }

    /**
     * Get all jawaban for this peserta.
     */
    public function jawaban(): HasMany
    {
        return $this->hasMany(Jawaban::class);
    }

    /**
     * Get all ujian that this peserta has taken.
     */
    public function ujian()
    {
        return $this->belongsToMany(Ujian::class, 'aktivitas_peserta', 'peserta_id', 'ujian_id')
                    ->withPivot(['status', 'waktu_login', 'waktu_submit'])
                    ->withTimestamps();
    }
}