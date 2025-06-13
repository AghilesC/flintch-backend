<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\UserPhoto;

class UserPhotoController extends Controller
{
    public function store(Request $request)
    {
        if (!$request->hasFile('photo')) {
            return response()->json(['status' => false, 'message' => 'Aucune photo envoyée.'], 400);
        }

        $file = $request->file('photo');

        if (!$file->isValid()) {
            return response()->json(['status' => false, 'message' => 'Fichier invalide.'], 400);
        }

        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('images', $filename, 'public');

        $photo = $request->user()->photos()->create([
            'photo_url' => url("/api/user/photo/$filename"), // ✅ route Laravel
            'path' => $path,
            'is_main' => $request->user()->photos()->count() === 0,
        ]);

        if ($photo->is_main) {
            $user = $request->user();
            $user->profile_photo = $photo->photo_url;
            $user->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Photo ajoutée',
            'photo' => $photo,
        ]);
    }

    public function index(Request $request)
    {
        $photos = $request->user()->photos()->get();

        return response()->json([
            'status' => true,
            'photos' => $photos,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $photo = UserPhoto::where('user_id', $request->user()->id)->find($id);

        if (!$photo) {
            return response()->json(['status' => false, 'message' => 'Photo non trouvée.'], 404);
        }

        if ($photo->path && Storage::disk('public')->exists($photo->path)) {
            Storage::disk('public')->delete($photo->path);
        }

        $photo->delete();

        return response()->json(['status' => true, 'message' => 'Photo supprimée.']);
    }

    public function setMain(Request $request, $id)
    {
        $user = $request->user();

        $photo = UserPhoto::where('user_id', $user->id)->find($id);

        if (!$photo) {
            return response()->json(['status' => false, 'message' => 'Photo non trouvée.'], 404);
        }

        $user->photos()->update(['is_main' => false]);

        $photo->is_main = true;
        $photo->save();

        $user->profile_photo = $photo->photo_url;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Photo définie comme principale.',
        ]);
    }
}
