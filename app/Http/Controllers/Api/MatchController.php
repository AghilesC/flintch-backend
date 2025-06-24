<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class MatchController extends Controller
{
    public function getPotentialMatches(Request $request)
    {
        $user = Auth::user();

        // Exclusion des utilisateurs déjà likés OU rejetés
        $excludedUserIds = UserMatch::where(function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->pluck('matched_user_id')->unique();

        $excludedUserIds->push($user->id); // On ne veut pas voir soi-même

        $potentialMatches = User::whereNotIn('id', $excludedUserIds)
            ->with('photos')
            ->select('id', 'name', 'email', 'birthdate', 'bio', 'location', 'created_at')
            ->paginate(10);

        $potentialMatches->getCollection()->transform(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'age' => $u->birthdate ? Carbon::parse($u->birthdate)->age : null,
                'bio' => $u->bio ?? null,
                'location' => $u->location ?? null,
                'interests' => [], // pas encore utilisé
                'photos' => $u->photos->pluck('photo_url')->toArray(),
                'created_at' => $u->created_at,
            ];
        });

        return response()->json($potentialMatches);
    }

    public function createMatch(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = Auth::user();
        $matchedUserId = $request->user_id;

        $existingMatch = UserMatch::where(function ($query) use ($user, $matchedUserId) {
            $query->where('user_id', $user->id)
                  ->where('matched_user_id', $matchedUserId);
        })->first();

        // Si l’autre utilisateur avait déjà liké avant
        $reverseMatch = UserMatch::where('user_id', $matchedUserId)
            ->where('matched_user_id', $user->id)
            ->first();

        if ($reverseMatch && !$reverseMatch->is_mutual) {
            $reverseMatch->update(['is_mutual' => true]);

            return response()->json([
                'message' => '🎉 C’est un match !',
                'is_mutual' => true,
                'match' => $reverseMatch,
            ]);
        }

        if (!$existingMatch) {
            $match = UserMatch::create([
                'user_id' => $user->id,
                'matched_user_id' => $matchedUserId,
                'is_mutual' => false,
                'matched_at' => now(),
            ]);

            return response()->json([
                'message' => 'Match enregistré',
                'is_mutual' => false,
                'match' => $match,
            ]);
        }

        return response()->json([
            'message' => 'Déjà liké',
            'is_mutual' => $existingMatch->is_mutual,
            'match' => $existingMatch,
        ]);
    }

    public function rejectUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = Auth::user();

        UserMatch::create([
            'user_id' => $user->id,
            'matched_user_id' => $request->user_id,
            'is_mutual' => false,
            'status' => 'rejected',
            'matched_at' => now(),
        ]);

        return response()->json(['message' => 'Utilisateur rejeté']);
    }

    public function getMatches(Request $request)
    {
        $user = Auth::user();

        $matches = UserMatch::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('matched_user_id', $user->id);
        })
        ->where('is_mutual', true)
        ->with(['user.photos', 'matchedUser.photos'])
        ->get()
        ->map(function ($match) use ($user) {
            $matchedUser = $match->user_id === $user->id ? $match->matchedUser : $match->user;

            return [
                'id' => $match->id,
                'user' => [
                    'id' => $matchedUser->id,
                    'name' => $matchedUser->name,
                    'email' => $matchedUser->email,
                    'photos' => $matchedUser->photos->pluck('photo_url')->toArray(),
                ],
                'matched_at' => $match->matched_at,
            ];
        });

        return response()->json($matches);
    }

    public function unmatch(Request $request, $matchId)
    {
        $user = Auth::user();

        $match = UserMatch::where('id', $matchId)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('matched_user_id', $user->id);
            })
            ->firstOrFail();

        $match->delete();

        return response()->json(['message' => 'Unmatch réussi']);
    }

    private function createMatchNotification($userId1, $userId2)
    {
        // À implémenter si tu veux utiliser le système de notifications Laravel
    }
}
