<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Immeuble;
use App\Models\Logement;
use App\Models\Media;
use Cloudinary\Cloudinary;
use Illuminate\Http\Request;

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

    private function cloudinary(): Cloudinary
    {
        return new Cloudinary(config('services.cloudinary.url'));
    }

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

        $file         = $request->file('fichier');
        $type         = str_starts_with((string) $file->getMimeType(), 'video') ? 'video' : 'photo';
        $resourceType = $type === 'video' ? 'video' : 'image';

        $result = $this->cloudinary()->uploadApi()->upload(
            $file->getRealPath(),
            ['folder' => 'toursenimmo', 'resource_type' => $resourceType]
        );

        $media = $parent->medias()->create([
            'type'       => $type,
            'libelle'    => $request->input('libelle'),
            'chemin'     => $result['secure_url'],
            'public_id'  => $result['public_id'],
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

        if ($media->public_id) {
            $resourceType = $media->type === 'video' ? 'video' : 'image';
            $this->cloudinary()->uploadApi()->destroy($media->public_id, ['resource_type' => $resourceType]);
        }

        $media->delete();

        return response()->json(['message' => 'Media supprime.']);
    }
}