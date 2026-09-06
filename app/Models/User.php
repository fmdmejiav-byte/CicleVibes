<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'rol_id',
        'nombre',
        'apellido',
        'telefono',
        'foto',
        'email',
        'email_verified_at',
        'password',
        'google_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Devuelve el nombre completo del usuario.
     */
    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombre.' '.$this->apellido);
    }

    /**
     * Indica si la cuenta dispone de una contraseña local.
     *
     * Los usuarios creados mediante "Continuar con Google" no tienen
     * contraseña local a menos que la establezcan más adelante.
     */
    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    /**
     * Define la notificación de restablecimiento de contraseña de CicleVibes.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Un usuario pertenece a un rol.
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    /**
     * Un usuario tiene muchas bicicletas.
     */
    public function bicicletas(): HasMany
    {
        return $this->hasMany(Bicicleta::class);
    }

    /**
     * Un usuario puede realizar muchos reportes.
     */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
    }

    /**
     * Un usuario puede crear muchas rutas.
     */
    public function rutas(): HasMany
    {
        return $this->hasMany(Ruta::class);
    }

    /**
     * Un usuario puede guardar muchas rutas favoritas.
     */
    public function favoritos(): HasMany
    {
        return $this->hasMany(Favorito::class);
    }

    /**
     * Un usuario puede hacer muchas publicaciones.
     */
    public function publicaciones(): HasMany
    {
        return $this->hasMany(Publicacion::class);
    }

    /**
     * Un usuario puede escribir muchos comentarios.
     */
    public function comentarios(): HasMany
    {
        return $this->hasMany(Comentario::class);
    }

    /**
     * Un usuario puede recibir muchas notificaciones.
     */
    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class);
    }
}
