<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use App\Services\AuditService;
use App\Services\UserManagementService;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * booting() s'exécute avant les traits : notre garde-fou voit les rôles avant que Spatie ne les détache.
     */
    protected static function booting(): void
    {
        // Garde-fou : le dernier compte à accès global actif ne peut être ni désactivé ni supprimé.
        static::deleting(static function (User $user): void {
            app(UserManagementService::class)->verifierNonDernierAdmin($user);
        });

        static::updating(static function (User $user): void {
            if ($user->isDirty('is_active') && ! $user->is_active) {
                app(UserManagementService::class)->verifierNonDernierAdmin($user);
            }
        });

        static::created(static function (User $user): void {
            app(AuditService::class)->journaliser('utilisateur.cree', $user, "Compte {$user->name} créé.");
        });

        // Noms des champs seulement : jamais les valeurs (le mot de passe n'apparaît nulle part).
        static::updated(static function (User $user): void {
            $champs = array_values(array_intersect(array_keys($user->getChanges()), ['name', 'email', 'password']));
            if ($champs !== []) {
                app(AuditService::class)->journaliser('utilisateur.modifie', $user, "Compte {$user->name} modifié.", ['champs' => $champs]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Règle unique d'équivalence ADMIN = RH_MANAGER : accès à tous les dossiers, tous périmètres.
     * Ne jamais dupliquer ce test de rôle ailleurs dans le code.
     */
    public function hasGlobalAccess(): bool
    {
        return $this->hasAnyRole(RoleName::globauxValeurs());
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function siteDepartements(): BelongsToMany
    {
        return $this->belongsToMany(SiteDepartement::class, 'site_departement_user')->withTimestamps();
    }
}
