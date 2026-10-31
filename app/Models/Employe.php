<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\RegleMetierException;
use App\Services\AuditService;
use App\Services\PerimetreService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employe extends Model
{
    use HasFactory;

    protected $table = 'employes';

    /**
     * Levé uniquement par EmployeService : l'affectation courante ne change que via l'historique.
     */
    public static bool $autoriserChangementAffectation = false;

    /** @var list<string> */
    protected $fillable = [
        'matricule', 'name', 'fonction', 'date_naissance', 'date_embauche', 'type_contrat_id',
        'categorie_employe', 'statut_employe', 'date_sortie', 'motif_sortie', 'telephone',
        'num_CNSS', 'num_CNI', 'niveau_etude', 'adresse', 'situation_familiale',
        'personne_contacter', 'lien_parental', 'telephone_parental', 'formation_certificat',
        'permis_conduite', 'categorie', 'handicap', 'societe_id', 'mis_a_disposition_societe_id', 'departement_id', 'site_id',
        'dossier_path', 'cree_par',
    ];

    /** @var list<string> */
    protected $hidden = ['num_CNSS', 'num_CNI'];

    protected static function booted(): void
    {
        static::updating(static function (Employe $employe): void {
            if (self::$autoriserChangementAffectation) {
                return;
            }

            if ($employe->isDirty(['societe_id', 'site_id', 'departement_id', 'fonction'])) {
                throw new RegleMetierException(
                    "L'affectation d'un employé ne se modifie que par une mutation ou une promotion approuvée."
                );
            }
        });

        // Modifications de la fiche : noms des champs seulement (jamais les valeurs, souvent personnelles).
        // Les changements d'affectation sont journalisés par EmployeService.
        static::updated(static function (Employe $employe): void {
            if (self::$autoriserChangementAffectation) {
                return;
            }

            $champs = array_values(array_diff(array_keys($employe->getChanges()), ['updated_at']));
            if ($champs !== []) {
                app(AuditService::class)->journaliser('employe.modifie', $employe, "Fiche de l'agent {$employe->matricule} modifiée.", ['champs' => $champs]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_embauche' => 'date',
            'date_sortie' => 'date',
            'handicap' => 'boolean',
        ];
    }

    protected function age(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->date_naissance?->age);
    }

    /**
     * Ancienneté en mois complets depuis la date d'embauche (ou de sortie).
     */
    protected function anciennete(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->date_embauche === null) {
                return null;
            }

            $fin = $this->date_sortie ?? now();

            return max(0, (int) $this->date_embauche->diffInMonths($fin, true));
        });
    }

    public function scopeDansPerimetre(Builder $query, User $user): Builder
    {
        return app(PerimetreService::class)->appliquer($query, $user);
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    /** Société cliente chez laquelle l'agent est mis à disposition (null s'il travaille pour sa propre entité). */
    public function societeMiseADisposition(): BelongsTo
    {
        return $this->belongsTo(Societe::class, 'mis_a_disposition_societe_id');
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(SiteAffectation::class, 'site_id');
    }

    public function typeContrat(): BelongsTo
    {
        return $this->belongsTo(TypeContrat::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(EmployeAffectation::class)->orderByDesc('date_debut')->orderByDesc('id');
    }

    public function affectationCourante(): HasOne
    {
        return $this->hasOne(EmployeAffectation::class)->whereNull('date_fin');
    }

    public function fichiers(): HasMany
    {
        return $this->hasMany(EmployeFichier::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'matricule', 'matricule');
    }

    public function conges(): HasMany
    {
        return $this->hasMany(Conge::class, 'matricule', 'matricule');
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class, 'matricule', 'matricule');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'matricule', 'matricule');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class, 'matricule', 'matricule');
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(Mutation::class, 'matricule', 'matricule');
    }
}
