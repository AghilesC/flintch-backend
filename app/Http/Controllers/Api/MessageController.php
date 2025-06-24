<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\User;
use App\Services\SendbirdService;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function sendMessage(Request $request)
    {
        try {
            \Log::info('📨 Requête sendMessage reçue', $request->all());

            $request->validate([
                'receiver_id' => 'required|exists:users,id',
                'message' => 'required|string',
            ]);

            $sender = auth()->user();
            $receiver = User::find($request->receiver_id);

            if (!$receiver) {
                return response()->json(['error' => 'Receiver not found'], 404);
            }

            \Log::info('📨 Sender:', ['id' => $sender->id]);
            \Log::info('📨 Receiver:', ['id' => $receiver->id]);

            // S'assurer que les utilisateurs existent dans Sendbird
            $sendbirdEnabled = true;
            $channelUrl = null;
            
            try {
                // Tester la connexion Sendbird
                if (!SendbirdService::testConnection()) {
                    \Log::warning('⚠️ Sendbird non disponible, utilisation locale uniquement');
                    $sendbirdEnabled = false;
                }
                
                if ($sendbirdEnabled) {
                    // Créer/mettre à jour l'expéditeur
                    SendbirdService::createOrUpdateUser(
                        $sender->id, 
                        $sender->name, 
                        $sender->profile_photo
                    );
                    \Log::info('✅ Utilisateur expéditeur créé/mis à jour dans Sendbird', ['id' => $sender->id]);
                    
                    // Créer/mettre à jour le destinataire
                    SendbirdService::createOrUpdateUser(
                        $receiver->id, 
                        $receiver->name, 
                        $receiver->profile_photo
                    );
                    \Log::info('✅ Utilisateur destinataire créé/mis à jour dans Sendbird', ['id' => $receiver->id]);
                    
                    // Créer ou obtenir le canal
                    $channelUrl = SendbirdService::getOrCreate1to1Channel($sender->id, $receiver->id);
                    \Log::info('✅ Canal obtenu:', ['channel_url' => $channelUrl]);
                    
                    // Envoyer le message via Sendbird
                    $sendbirdResponse = SendbirdService::sendMessage($channelUrl, $sender->id, $request->message);
                    \Log::info('✅ Message envoyé via Sendbird:', ['response' => $sendbirdResponse]);
                }
                
            } catch (\Exception $e) {
                \Log::error('❌ Erreur Sendbird (non bloquant):', [
                    'error' => $e->getMessage()
                ]);
                $sendbirdEnabled = false;
                // On continue pour sauvegarder le message localement
            }

            // Sauvegarder en base de données locale
            $msg = Message::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'message' => $request->message,
                'sent_at' => now(),
            ]);

            return response()->json([
                'success' => true, 
                'message' => $msg,
                'channel_url' => $channelUrl,
                'sendbird_enabled' => $sendbirdEnabled
            ]);

        } catch (\Throwable $e) {
            \Log::error('❌ Erreur MessageController:', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Erreur serveur',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getMessagesWithUser($userId)
    {
        $authId = Auth::id();

        $messages = Message::where(function ($query) use ($authId, $userId) {
                $query->where('sender_id', $authId)->where('receiver_id', $userId);
            })
            ->orWhere(function ($query) use ($authId, $userId) {
                $query->where('sender_id', $userId)->where('receiver_id', $authId);
            })
            ->orderBy('sent_at')
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'message' => $msg->message,
                    'sender_id' => $msg->sender_id,
                    'receiver_id' => $msg->receiver_id,
                    'sent_at' => optional($msg->sent_at)->format('H:i'),
                    'created_at' => optional($msg->created_at)->format('H:i'),
                ];
            });

        return response()->json([
            'messages' => $messages,
            'current_user_id' => $authId,
        ]);
    }
}