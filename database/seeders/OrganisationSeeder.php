<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Departement;
use App\Models\Societe;
use App\Models\TypeContrat;
use Illuminate\Database\Seeder;

class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['DLS' => 'Djibouti Labour Service', 'SGTD' => 'SGTD', 'DMP' => 'DMP', 'IDIPO' => 'IDIPO'] as $code => $nom) {
            Societe::query()->firstOrCreate(['code' => $code], ['name' => $nom, 'is_active' => true]);
        }

        $dls = Societe::query()->where('code', 'DLS')->firstOrFail();
        $departements = [
            'Direction Générale' => 'DG',
            'Ressources humaines' => 'RH',
            'Finance' => 'FIN',
            'Opérations' => 'OPS',
        ];
        foreach ($departements as $nom => $abrege) {
            Departement::query()->firstOrCreate(
                ['societe_id' => $dls->id, 'name' => $nom],
                ['abrege' => $abrege, 'is_active' => true],
            );
        }

        $contrats = [
            'JOURNALIER SGTD', 'JOURNALIER DMP', 'PERMANENT DLS',
            'PERMANENT SGTD', 'PERMANENT DMP', 'FREE ZONE-PERMANENT',
        ];
        foreach ($contrats as $nom) {
            TypeContrat::query()->firstOrCreate(['name' => $nom], ['is_active' => true]);
        }
    }
}
