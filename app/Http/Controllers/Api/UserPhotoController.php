<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\UserPhoto;

class UserPhotoController extends Controller
{
    // Upload d’une nouvelle photo
public function upload(Request $request)
{
    $request->validate([
        'photo' => 'required|image|max:5120',
    ]);

    $user = $request->user();

    $path = $request->file('photo')->store('images', 'public');
    $url = asset('storage/' . $path);

    $photo = new UserPhoto();
    $photo->user_id = $user->id;
    $photo->path = $path;
    $photo->photo_url = $url; // ← CORRECTION ICI
    $photo->is_main = !$user->photos()->exists();
    $photo->save();

    return response()->json([
        'status' => true,
        'message' => 'Photo uploaded',
        'photo' => [
            'id' => $photo->id,
            'url' => $url,
            'is_main' => $photo->is_main,
        ]
    ]);
}


    // Récupérer toutes les photos de l'utilisateur
    public function getPhotos()
    {
        $user = Auth::user();
        $photos = $user->photos()->get()->map(function ($photo) {
            return [
                'id' => $photo->id,
                'is_main' => $photo->is_main,
                'photo_url' => $photo->path ? Storage::url($photo->path) : null, // ✅ URL publique
            ];
        });

        return response()->json([
            'status' => true,
            'photos' => $photos,
        ]);
    }

    // Supprimer une photo
    public function deletePhoto($id)
    {
        $user = Auth::user();
        $photo = UserPhoto::where('id', $id)->where('user_id', $user->id)->first();

        if (!$photo) {
            return response()->json(['status' => false, 'message' => 'Photo not found'], 404);
        }

        if ($photo->path && Storage::disk('public')->exists($photo->path)) {
            Storage::disk('public')->delete($photo->path);
        }

        $photo->delete();

        return response()->json(['status' => true, 'message' => 'Photo deleted']);
    }

    // Définir une photo comme principale
    public function setMainPhoto($id)
    {
        $user = Auth::user();
        $photo = UserPhoto::where('id', $id)->where('user_id', $user->id)->first();

        if (!$photo) {
            return response()->json(['status' => false, 'message' => 'Photo not found'], 404);
        }

        // Réinitialise les autres photos
        UserPhoto::where('user_id', $user->id)->update(['is_main' => false]);

        // Met celle-ci comme principale
        $photo->is_main = true;
        $photo->save();

        return response()->json(['status' => true, 'message' => 'Main photo set']);
    }

    public function index(Request $request)
{
    $user = $request->user();

    $photos = $user->photos()->orderByDesc('is_main')->get()->map(function ($photo) {
        return [
            'id' => $photo->id,
            'is_main' => $photo->is_main,
            'url' => asset('storage/' . $photo->path),
        ];
    });

    return response()->json([
        'status' => true,
        'photos' => $photos
    ]);
}

}
