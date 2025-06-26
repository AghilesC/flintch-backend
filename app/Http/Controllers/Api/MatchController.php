<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class MatchController extends Controller
{
    public function getPotentialMatches(Request $request)
    {
        try {
            $user = Auth::user();

            // Exclusion des utilisateurs deja likes OU rejetes
            $excludedUserIds = UserMatch::where(function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->pluck('matched_user_id')->unique();

            $excludedUserIds->push($user->id); // On ne veut pas voir soi-meme

            $potentialMatches = User::whereNotIn('id', $excludedUserIds)
                ->with('photos')
                ->select('id', 'name', 'email', 'birthdate', 'bio', 'location', 'sports', 'created_at')
                ->paginate(10);

            $potentialMatches->getCollection()->transform(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'age' => $u->birthdate ? Carbon::parse($u->birthdate)->age : null,
                    'bio' => $u->bio ?? null,
                    'location' => $u->location ?? null,
                    'interests' => [], // pas encore utilise
                    'photos' => $u->photos ? $u->photos->pluck('photo_url')->toArray() : [],
                    'sports' => $u->sports ?? [], // Sports de l utilisateur
                    'created_at' => $u->created_at,
                ];
            });

            return response()->json($potentialMatches);
            
        } catch (\Exception $e) {
            \Log::error('Erreur getPotentialMatches:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'error' => 'Erreur lors du chargement des profils',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function createMatch(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);

            $user = Auth::user();
            $matchedUserId = $request->user_id;

            $existingMatch = UserMatch::where(function ($query) use ($user, $matchedUserId) {
                $query->where('user_id', $user->id)
                      ->where('matched_user_id', $matchedUserId);
            })->first();

            // Si l'autre utilisateur avait deja like avant
            $reverseMatch = UserMatch::where('user_id', $matchedUserId)
                ->where('matched_user_id', $user->id)
                ->first();

            if ($reverseMatch && !$reverseMatch->is_mutual) {
                $reverseMatch->update(['is_mutual' => true]);

                return response()->json([
                    'message' => 'C est un match !',
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
                    'message' => 'Match enregistre',
                    'is_mutual' => false,
                    'match' => $match,
                ]);
            }

            return response()->json([
                'message' => 'Deja like',
                'is_mutual' => $existingMatch->is_mutual,
                'match' => $existingMatch,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Erreur createMatch:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'error' => 'Erreur lors de la creation du match',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function rejectUser(Request $request)
    {
        try {
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

            return response()->json(['message' => 'Utilisateur rejete']);
            
        } catch (\Exception $e) {
            \Log::error('Erreur rejectUser:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'error' => 'Erreur lors du rejet',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getMatches(Request $request)
    {
        try {
            $user = Auth::user();
            $includeMessages = $request->has('include_messages');

            $matches = UserMatch::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('matched_user_id', $user->id);
            })
            ->where('is_mutual', true)
            ->with(['user.photos', 'matchedUser.photos'])
            ->get()
            ->map(function ($match) use ($user, $includeMessages) {
                $matchedUser = $match->user_id === $user->id ? $match->matchedUser : $match->user;

                $result = [
                    'id' => $match->id,
                    'user' => [
                        'id' => $matchedUser->id,
                        'name' => $matchedUser->name,
                        'email' => $matchedUser->email,
                        'photos' => $matchedUser->photos ? $matchedUser->photos->pluck('photo_url')->toArray() : [],
                    ],
                    'matched_at' => $match->matched_at ? $match->matched_at->toISOString() : now()->toISOString(),
                ];

                // Si on demande les messages, les inclure
                if ($includeMessages) {
                    try {
                        // Verifier si la table messages existe et a la colonne is_read
                        if (\Schema::hasTable('messages') && \Schema::hasColumn('messages', 'is_read')) {
                            // Recuperer le dernier message entre ces deux utilisateurs
                            $lastMessage = Message::where(function ($query) use ($user, $matchedUser) {
                                    $query->where('sender_id', $user->id)->where('receiver_id', $matchedUser->id);
                                })
                                ->orWhere(function ($query) use ($user, $matchedUser) {
                                    $query->where('sender_id', $matchedUser->id)->where('receiver_id', $user->id);
                                })
                                ->orderBy('sent_at', 'desc')
                                ->first();

                            if ($lastMessage) {
                                $result['last_message'] = [
                                    'id' => $lastMessage->id,
                                    'content' => $lastMessage->message,
                                    'sender_id' => $lastMessage->sender_id,
                                    'created_at' => $lastMessage->sent_at ? $lastMessage->sent_at->toISOString() : now()->toISOString(),
                                ];

                                // Compter les messages non lus (messages du partenaire que je n ai pas lus)
                                $unreadCount = Message::where('sender_id', $matchedUser->id)
                                    ->where('receiver_id', $user->id)
                                    ->where('is_read', false)
                                    ->count();

                                $result['unread_count'] = $unreadCount;
                            }
                        }
                    } catch (\Exception $e) {
                        // Si erreur avec les messages, on continue sans les inclure
                        \Log::warning('Erreur recuperation messages pour match ' . $match->id . ': ' . $e->getMessage());
                    }
                }

                return $result;
            });

            return response()->json($matches);
            
        } catch (\Exception $e) {
            \Log::error('Erreur getMatches:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'error' => 'Erreur lors de la recuperation des matches',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead(Request $request, $matchId)
    {
        try {
            $user = Auth::user();
            
            // Verifier si la table messages existe et a la colonne is_read
            if (!\Schema::hasTable('messages') || !\Schema::hasColumn('messages', 'is_read')) {
                return response()->json(['error' => 'Messages table not properly configured'], 500);
            }
            
            // Trouver le match
            $match = UserMatch::find($matchId);
            if (!$match) {
                return response()->json(['error' => 'Match not found'], 404);
            }

            // Verifier que l utilisateur fait partie de ce match
            if ($match->user_id !== $user->id && $match->matched_user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Determiner qui est le partenaire
            $partnerId = $match->user_id === $user->id ? $match->matched_user_id : $match->user_id;

            // Marquer tous les messages du partenaire comme lus
            Message::where('sender_id', $partnerId)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            \Log::error('Erreur markAsRead:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'error' => 'Erreur lors du marquage comme lu',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function unmatch(Request $request, $matchId)
    {
        try {
            $user = Auth::user();

            $match = UserMatch::where('id', $matchId)
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('matched_user_id', $user->id);
                })
                ->firstOrFail();

            $match->delete();

            return response()->json(['message' => 'Unmatch reussi']);
            
        } catch (\Exception $e) {
            \Log::error('Erreur unmatch:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'error' => 'Erreur lors de l unmatch',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function createMatchNotification($userId1, $userId2)
    {
        // A implementer si tu veux utiliser le systeme de notifications Laravel
    }
}