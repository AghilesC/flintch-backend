<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SendbirdService
{
    protected static $apiToken = 'fe12fad14050c8dccf1f5946915ea0ddd4241e1d';
    protected static $appId = '98D69269-98AC-43CE-92EA-622E9038F231';
    protected static $baseUrl = 'https://api-98D69269-98AC-43CE-92EA-622E9038F231.sendbird.com/v3';

    public static function getOrCreate1to1Channel($userId1, $userId2)
    {
        $channelName = 'channel_' . min($userId1, $userId2) . '_' . max($userId1, $userId2);

        // D'abord, essayons de trouver un canal existant entre ces deux utilisateurs
        $listResponse = Http::withHeaders([
            'Api-Token' => self::$apiToken
        ])->get(self::$baseUrl . '/group_channels', [
            'distinct_mode' => 'distinct',
            'user_ids' => implode(',', [(string) $userId1, (string) $userId2]),
            'limit' => 1
        ]);

        if ($listResponse->successful() && count($listResponse['channels']) > 0) {
            // Un canal existe déjà
            return $listResponse['channels'][0]['channel_url'];
        }

        // S'assurer que les utilisateurs existent dans Sendbird
        $user1Check = Http::withHeaders(['Api-Token' => self::$apiToken])
            ->get(self::$baseUrl . '/users/' . $userId1);
        
        $user2Check = Http::withHeaders(['Api-Token' => self::$apiToken])
            ->get(self::$baseUrl . '/users/' . $userId2);

        if (!$user1Check->successful() || !$user2Check->successful()) {
            \Log::error('⚠️ Un ou plusieurs utilisateurs n\'existent pas dans Sendbird', [
                'user1_exists' => $user1Check->successful(),
                'user2_exists' => $user2Check->successful()
            ]);
        }

        // Créer un nouveau canal - Essayons sans is_distinct d'abord
        $payload = [
            'name' => $channelName,
            'channel_url' => $channelName,
            'user_ids' => [(string) $userId1, (string) $userId2],
            'custom_type' => '1to1',
            'is_public' => false,
            'is_super' => false,
            'is_ephemeral' => false
        ];

        $response = Http::withHeaders([
            'Api-Token' => self::$apiToken,
            'Content-Type' => 'application/json'
        ])->post(self::$baseUrl . '/group_channels', $payload);

        if (!$response->successful()) {
            \Log::error('❌ Erreur Sendbird:', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload
            ]);
            throw new \Exception('❌ Erreur création canal Sendbird: ' . $response->body());
        }

        return $response['channel_url'];
    }

    public static function sendMessage($channelUrl, $senderId, $message)
    {
        $payload = [
            'message_type' => 'MESG',
            'user_id' => (string) $senderId,
            'message' => $message,
        ];

        $res = Http::withHeaders([
            'Api-Token' => self::$apiToken,
            'Content-Type' => 'application/json'
        ])->post(self::$baseUrl . "/group_channels/{$channelUrl}/messages", $payload);

        if (!$res->successful()) {
            \Log::error('❌ Erreur envoi message Sendbird:', [
                'status' => $res->status(),
                'body' => $res->body(),
                'payload' => $payload
            ]);
            throw new \Exception('❌ Erreur envoi message Sendbird: ' . $res->body());
        }

        return $res->json();
    }

    /**
     * Créer ou mettre à jour un utilisateur Sendbird
     */
    public static function createOrUpdateUser($userId, $nickname, $profileUrl = null)
    {
        // D'abord vérifier si l'utilisateur existe
        $checkResponse = Http::withHeaders([
            'Api-Token' => self::$apiToken
        ])->get(self::$baseUrl . '/users/' . $userId);

        $payload = [
            'user_id' => (string) $userId,
            'nickname' => $nickname,
            'profile_url' => $profileUrl ?: ''
        ];

        if ($checkResponse->successful()) {
            // L'utilisateur existe, on le met à jour
            $response = Http::withHeaders([
                'Api-Token' => self::$apiToken,
                'Content-Type' => 'application/json'
            ])->put(self::$baseUrl . '/users/' . $userId, $payload);
        } else {
            // L'utilisateur n'existe pas, on le crée
            $response = Http::withHeaders([
                'Api-Token' => self::$apiToken,
                'Content-Type' => 'application/json'
            ])->post(self::$baseUrl . '/users', $payload);
        }

        if (!$response->successful()) {
            \Log::error('❌ Erreur création/update utilisateur Sendbird:', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload
            ]);
            throw new \Exception('❌ Erreur création utilisateur Sendbird: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Tester la connexion à Sendbird
     */
    public static function testConnection()
    {
        try {
            $response = Http::withHeaders([
                'Api-Token' => self::$apiToken
            ])->get(self::$baseUrl . '/applications');

            if ($response->successful()) {
                \Log::info('✅ Connexion Sendbird OK', [
                    'app_id' => $response['application_id'] ?? 'N/A'
                ]);
                return true;
            } else {
                \Log::error('❌ Échec connexion Sendbird', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            \Log::error('❌ Exception connexion Sendbird', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}