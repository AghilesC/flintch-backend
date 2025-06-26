<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\UserPhoto;
use Illuminate\Support\Facades\Http; 
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function socialLogin(Request $request)
    {
        $provider = $request->input('provider');
        $token = $request->input('token');

        // GOOGLE
        if ($provider === 'google') {
            $googleRes = Http::get('https://www.googleapis.com/oauth2/v3/userinfo', [
                'access_token' => $token
            ]);
            if (!$googleRes->ok()) {
                return response()->json(['message' => 'Token Google invalide'], 401);
            }
            $googleUser = $googleRes->json();
            $email = $googleUser['email'] ?? null;
            $name = $googleUser['name'] ?? null;
            $firstName = $name ? explode(' ', trim($name))[0] : null;

            if (!$email) {
                return response()->json(['message' => 'Impossible de récupérer le mail Google'], 400);
            }

            $user = User::where('email', $email)->first();
            if (!$user) {
                // User NON créé ici
                return response()->json([
                    'needRegister' => true,
                    'name' => $firstName ?? $email,
                    'email' => $email,
                    'provider' => $provider,
                    'provider_token' => $token,
                ]);
            }

            $tokenApi = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'token' => $tokenApi,
                'user' => $this->formatUserResponse($user),
                'needRegister' => false,
            ]);
        }

        // FACEBOOK
        if ($provider === 'facebook') {
            $fbRes = Http::get('https://graph.facebook.com/me', [
                'fields' => 'id,name,email,picture',
                'access_token' => $token
            ]);
            if (!$fbRes->ok()) {
                return response()->json(['message' => 'Token Facebook invalide'], 401);
            }
            $fbUser = $fbRes->json();
            $email = $fbUser['email'] ?? null;
            $name = $fbUser['name'] ?? null;
            $firstName = $name ? explode(' ', trim($name))[0] : null;

            if (!$email) {
                return response()->json(['message' => 'Impossible de récupérer le mail Facebook'], 400);
            }

            $user = User::where('email', $email)->first();
            if (!$user) {
                // User NON créé ici
                return response()->json([
                    'needRegister' => true,
                    'name' => $firstName ?? $email,
                    'email' => $email,
                    'provider' => $provider,
                    'provider_token' => $token,
                ]);
            }

            $tokenApi = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'token' => $tokenApi,
                'user' => $this->formatUserResponse($user),
                'needRegister' => false,
            ]);
        }

        return response()->json(['message' => 'Provider non supporté'], 400);
    }

    public function socialRegister(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'birthdate'      => 'required|date',
            'email'          => 'required|email|unique:users',
            'phone'          => 'nullable|string|max:20',
            'profile_photo'  => 'nullable|string|max:255',
            'gender'         => 'nullable|string|max:50',
            'height'         => 'nullable|string|max:10',
            'weight'         => 'nullable|string|max:10',
            'sports'         => 'nullable|array',
            'fitness_level'  => 'nullable|string|max:50',
            'goals'          => 'nullable|array',
            'provider'       => 'required|string|in:google,facebook',
            'provider_token' => 'required|string',
        ]);

        $user = User::create([
            'name'           => $data['name'],
            'birthdate'      => $data['birthdate'],
            'email'          => $data['email'],
            'password'       => bcrypt(\Illuminate\Support\Str::random(20)),
            'phone'          => $data['phone'] ?? null,
            'profile_photo'  => $data['profile_photo'] ?? null,
            'gender'         => $data['gender'] ?? null,
            'height'         => $data['height'] ?? null,
            'weight'         => $data['weight'] ?? null,
            'sports'         => $data['sports'] ?? [],
            'fitness_level'  => $data['fitness_level'] ?? null,
            'goals'          => $data['goals'] ?? [],
        ]);

        $tokenApi = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $this->formatUserResponse($user),
            'token' => $tokenApi,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'          => 'nullable|string|max:255',
            'birthdate'     => 'nullable|date',
            'gender'        => 'nullable|string|max:50',
            'height'        => 'nullable|string|max:10',
            'weight'        => 'nullable|string|max:10',
            'sports'        => 'nullable|array',
            'fitness_level' => 'nullable|string|max:50',
            'goals'         => 'nullable|array',
            'availability'  => 'nullable|array',
            'location'      => 'nullable|string|max:255',
            'latitude'      => 'nullable|numeric',
            'longitude'     => 'nullable|numeric',
            'phone'         => 'nullable|string|max:20',
            'profile_photo' => 'nullable|string|max:255',
        ]);

        $user->update([
            ...$data,
            'sports' => $data['sports'] ?? $user->sports ?? [],
            'goals'  => $data['goals'] ?? $user->goals ?? [],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Profil mis à jour avec succès',
            'user' => $this->formatUserResponse($user->fresh())
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['status' => true, 'message' => 'Déconnecté']);
    }

    /**
     * ✅ MÉTHODE PRINCIPALE : Retourne l'utilisateur authentifié avec TOUS les champs
     */
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json($this->formatUserResponse($user));
    }

    /**
     * ✅ MÉTHODE POUR /api/user : Même que me() 
     */
    public function getAuthenticatedUser(Request $request)
    {
        $user = $request->user();
        return response()->json($this->formatUserResponse($user));
    }

    /**
     * 🔧 MÉTHODE PRIVÉE : Formate la réponse utilisateur avec TOUS les champs nécessaires
     */
    private function formatUserResponse(User $user)
    {
        // 🔍 DEBUG - Log pour voir les données brutes
        Log::info('=== USER RESPONSE DEBUG ===');
        Log::info('User ID: ' . $user->id);
        Log::info('User name: ' . $user->name);
        Log::info('Raw sports from DB: ' . $user->getRawOriginal('sports'));
        Log::info('Raw goals from DB: ' . $user->getRawOriginal('goals'));
        Log::info('Casted sports: ', $user->sports ?? []);
        Log::info('Casted goals: ', $user->goals ?? []);
        Log::info('============================');

        // ✅ Retour formaté avec tous les champs nécessaires
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo' => $user->profile_photo,
            'gender' => $user->gender,
            'fitness_level' => $user->fitness_level,
            'birthdate' => $user->birthdate,
            'height' => $user->height,
            'weight' => $user->weight,
            'phone' => $user->phone,
            'location' => $user->location,
            'latitude' => $user->latitude,
            'longitude' => $user->longitude,
            'is_premium' => $user->is_premium,
            'bio' => $user->bio,
            
            // ✅ CHAMPS CRITIQUES : Force un array même si NULL en DB
            'sports' => $user->sports ?? [],
            'goals' => $user->goals ?? [],
            'availability' => $user->availability ?? [],
            
            // 🔍 DEBUG TEMPORAIRE : Voir les données brutes
            '_debug' => [
                'raw_sports' => $user->getRawOriginal('sports'),
                'raw_goals' => $user->getRawOriginal('goals'),
                'sports_type' => gettype($user->sports),
                'goals_type' => gettype($user->goals),
                'sports_count' => is_array($user->sports) ? count($user->sports) : 0,
                'goals_count' => is_array($user->goals) ? count($user->goals) : 0,
            ]
        ];
    }

    public function setMainPhoto(Request $request)
    {
        $user = $request->user();
        $photoId = $request->input('photo_id');

        // Vérifie que la photo appartient bien à l'utilisateur
        $photo = $user->photos()->where('id', $photoId)->first();

        if (!$photo) {
            return response()->json(['status' => false, 'message' => 'Photo non trouvée'], 404);
        }

        // Réinitialise toutes les photos principales
        $user->photos()->update(['is_main' => false]);

        // Définit la photo principale
        $photo->is_main = true;
        $photo->save();

        // Met à jour le champ `profile_photo` de l'utilisateur
        $user->profile_photo = $photo->photo_url;
        $user->save();

        return response()->json(['status' => true, 'message' => 'Photo principale mise à jour']);
    }

    public function setPreferences(Request $request)
    {
        $user = $request->user();
        $user->update($request->only(['gender_preference', 'sports_preference']));
        return response()->json(['status' => true, 'message' => 'Préférences mises à jour.']);
    }

    public function setLocation(Request $request)
    {
        $user = $request->user();
        $user->update($request->only(['location', 'latitude', 'longitude']));
        return response()->json(['status' => true, 'message' => 'Localisation mise à jour.']);
    }

    /**
     * ✅ MÉTHODE POUR AJOUTER DES DONNÉES DE TEST
     */
    public function addTestData(Request $request)
    {
        $user = $request->user();
        
        $user->update([
            'sports' => ['Tennis', 'Course à pied', 'Natation', 'CrossFit'],
            'goals' => ['Perte de poids', 'Cardio', 'Bien-être', 'Renforcement musculaire'],
            'gender' => $user->gender ?? 'female',
            'fitness_level' => $user->fitness_level ?? 'intermediate'
        ]);

        Log::info('Test data added to user: ' . $user->id);

        return response()->json([
            'status' => true,
            'message' => 'Données de test ajoutées avec succès',
            'user' => $this->formatUserResponse($user->fresh())
        ]);
    }

    /**
     * ✅ MÉTHODES SUPPLÉMENTAIRES (vos méthodes existantes)
     */
    public function uploadPhoto(Request $request)
    {
        // Votre logique existante pour upload photo
        return response()->json(['status' => true, 'message' => 'Photo uploadée']);
    }

    public function reportUser(Request $request)
    {
        // Votre logique existante
        return response()->json(['status' => true, 'message' => 'Utilisateur signalé']);
    }

    public function blockUser(Request $request)
    {
        // Votre logique existante
        return response()->json(['status' => true, 'message' => 'Utilisateur bloqué']);
    }

    public function unblockUser(Request $request)
    {
        // Votre logique existante
        return response()->json(['status' => true, 'message' => 'Utilisateur débloqué']);
    }

    public function getBlockedUsers(Request $request)
    {
        // Votre logique existante
        return response()->json([]);
    }

    public function boostProfile(Request $request)
    {
        // Votre logique existante
        return response()->json(['status' => true, 'message' => 'Profil boosté']);
    }

    public function purchasePremium(Request $request)
    {
        // Votre logique existante
        return response()->json(['status' => true, 'message' => 'Premium activé']);
    }

    public function sendFeedback(Request $request)
    {
        // Votre logique existante
        return response()->json(['status' => true, 'message' => 'Feedback envoyé']);
    }
}