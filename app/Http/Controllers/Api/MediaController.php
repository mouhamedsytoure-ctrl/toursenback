<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Immeuble;
use App\Models\Logement;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class MediaController extends Controller
{
    private array $map = [
        'immeuble' => Immeuble::class,
        'logement' => Logement::class,
        'contrat'  => Contrat::class,
    ];

    private function moduleDe(string $class): string
    {
        return match ($class) {
            Immeuble::class => 'immeubles',
            Logement::class => 'logements',
            Contrat::class  => 'contrats',
            default         => 'immeubles',
        };
    }

    /**
     * POST /api/medias
     *   fichier        : photo / video
     *   mediable_type  : "immeuble" | "logement" | "contrat"
     *   mediable_id    : id du parent
     *   libelle        : (optionnel) "piece_identite" | "signature" | null
     *   couverture     : 0/1 (optionnel)
     */
    public function store(Request $request)
    {
        $request->validate([
            'fichier'       => ['required', 'file', 'max:51200'],
            'mediable_type' => ['required', 'in:immeuble,logement,contrat'],
            'mediable_id'   => ['required', 'integer'],
            'libelle'       => ['nullable', 'string', 'max:255'],
            'couverture'    => ['nullable', 'boolean'],
        ]);

        $class  = $this->map[$request->mediable_type];
        $parent = $class::findOrFail($request->mediable_id);
        $module = $this->moduleDe($class);

        abort_unless(
            $request->user()->hasPermission($module, 'update')
            || $request->user()->hasPermission($module, 'create')
            || $request->user()->hasPermission('locataires', 'create'),
            403,
            'Action non autorisee.'
        );

        $file = $request->file('fichier');
        $type = str_starts_with((string) $file->getMimeType(), 'video') ? 'video' : 'photo';

        $uploaded = Cloudinary::upload($file->getRealPath(), [
            'folder'        => 'toursenimmo',
            'resource_type' => 'auto',
        ]);

        $media = $parent->medias()->create([
            'type'       => $type,
            'libelle'    => $request->input('libelle'),
            'chemin'     => $uploaded->getSecurePath(),
            'couverture' => $request->boolean('couverture'),
        ]);

        return response()->json($media, 201);
    }

    public function setCouverture(Request $request, Media $media)
    {
        $module = $this->moduleDe($media->mediable_type);
        abort_unless($request->user()->hasPermission($module, 'update'), 403);

        Media::where('mediable_type', $media->mediable_type)
            ->where('mediable_id', $media->mediable_id)
            ->update(['couverture' => false]);

        $media->update(['couverture' => true]);

        return response()->json($media);
    }

    public function destroy(Request $request, Media $media)
    {
        $module = $this->moduleDe($media->mediable_type);
        abort_unless(
            $request->user()->hasPermission($module, 'delete') || $request->user()->isSuperAdmin(),
            403
        );

        if (str_starts_with($media->chemin, 'http')) {
            // Fichier Cloudinary : extraire le public_id et supprimer
            if (preg_match('/\/upload\/(?:v\d+\/)?(.+)$/', parse_url($media->chemin, PHP_URL_PATH), $m)) {
                $publicId = pathinfo($m[1], PATHINFO_FILENAME);
                Cloudinary::destroy($publicId, ['resource_type' => $media->type === 'video' ? 'video' : 'image']);
            }
        } else {
            Storage::disk('public')->delete($media->chemin);
        }
        $media->delete();

        return response()->json(['message' => 'Media supprime.']);
    }
}
