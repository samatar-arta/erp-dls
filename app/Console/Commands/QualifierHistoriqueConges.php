<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\HistoriqueCongesService;
use Illuminate\Console\Command;

class QualifierHistoriqueConges extends Command
{
    protected $signature = 'dls:qualifier-historique-conges
        {--appliquer : Écrit les changements (sans cette option, simulation seule)}
        {--soldes : Recalcule aussi les jours pris des soldes de congé annuel}';

    protected $description = 'Qualifie rétroactivement les congés annuels (titre de congé / autorisation d\'absence) et recalcule les soldes.';

    public function handle(HistoriqueCongesService $historique): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $this->line($appliquer ? 'Mode ÉCRITURE.' : 'Simulation : aucune écriture (ajoutez --appliquer).');

        $q = $historique->qualifier($appliquer);
        $this->info(sprintf('Qualification : %d congé(s) annuel(s) sans qualification — %d titre(s) de congé, %d autorisation(s) d\'absence.', $q['total'], $q['titres'], $q['autorisations']));

        if ($q['non_conformes'] !== []) {
            $this->warn(count($q['non_conformes']).' congé(s) historique(s) de moins de 5 jours (non conformes à la règle actuelle, qualifiés autorisation d\'absence, non modifiés) :');
            $this->table(['Congé', 'Matricule', 'Jours'], array_map(fn (array $l): array => [$l['id'], $l['matricule'], $l['jours']], $q['non_conformes']));
        }

        if ($this->option('soldes')) {
            $s = $historique->recalculerSoldes($appliquer);
            $this->info(sprintf('Soldes : %d vérifié(s), %d écart(s)%s.', $s['verifies'], count($s['ecarts']), $appliquer ? ' corrigé(s)' : ''));

            if ($s['ecarts'] !== []) {
                $this->table(['Matricule', 'Année', 'Jours pris actuels', 'Jours pris attendus', 'Restant après'], array_map(
                    fn (array $l): array => [$l['matricule'], $l['annee'], $l['actuel'], $l['attendu'], $l['restant']],
                    $s['ecarts'],
                ));
            }

            if ($s['sans_solde'] !== []) {
                $this->warn(count($s['sans_solde']).' agent(s)/année(s) avec des jours déduits mais sans ligne de solde (non créée) :');
                $this->table(['Matricule', 'Année', 'Jours attendus'], array_map(fn (array $l): array => [$l['matricule'], $l['annee'], $l['attendu']], $s['sans_solde']));
            }
        }

        return self::SUCCESS;
    }
}
