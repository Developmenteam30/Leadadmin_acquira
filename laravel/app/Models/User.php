<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'idUser';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'fullName',
        'password',
        'idCompany',
        'level',
        'email',
        'accessBits',
        'isArchived',
        'emailBits',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accessBits' => 'integer',
            'emailBits' => 'integer',
            'isArchived' => 'boolean',
            'level' => 'integer',
            'idCompany' => 'integer',
        ];
    }

    /**
     * Verify password against existing PHP password_hash format
     * Note: We don't use Laravel's password hashing because we need to verify
     * against existing PHP password_hash() hashes
     */
    public function verifyPassword($password)
    {
        return password_verify($password, $this->password);
    }
}
