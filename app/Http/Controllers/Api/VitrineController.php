<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Immeuble;

class VitrineController extends Controller
{
    // GET /api/public/immeubles  (PUBLIC, sans connexion)
    // Liste des immeubles avec le nombre de logements disponibles.
    public function immeubles()
    {
        $immeubles = Immeuble::withCount([
            'logements as disponibles_count' => fn ($q) => $q->where('statut', 'disponible'),
        ])->with('medias')->get(['id', 'nom', 'adresse', 'ville', 'photo_couverture']);

        return response()->json($immeubles);
    }

    // GET /api/public/immeubles/{immeuble}  (PUBLIC)
    // Detail public : seulement les logements DISPONIBLES, groupables par etage cote client.
    public function show(Immeuble $immeuble)
    {
        $immeuble->load([
            'medias',
            'logements' => fn ($q) => $q->where('statut', 'disponible')->with('medias'),
        ]);

        return response()->json($immeuble);
    }
}
