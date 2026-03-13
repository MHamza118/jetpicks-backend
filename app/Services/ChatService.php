<?php

namespace App\Services;

use App\Models\ChatRoom;
use App\Models\ChatMessage;

class ChatService
{
    public function createChatRoom(string $orderId, string $ordererId, string $pickerId): ChatRoom
    {
        $existingRoom = ChatRoom::where('order_id', $orderId)->first();
        if ($existingRoom) {
            return $existingRoom;
        }
        return ChatRoom::create([
            'order_id' => $orderId,
            'orderer_id' => $ordererId,
            'picker_id' => $pickerId,
        ]);
    }

    public function getChatRooms(string $userId, int $page = 1, int $limit = 20): array
    {
        $query = ChatRoom::where(function ($q) use ($userId) {
            $q->where('orderer_id', $userId)
                ->orWhere('picker_id', $userId);
        })
        ->with(['order', 'orderer', 'picker'])
        ->orderBy('updated_at', 'desc');

        $total = $query->count();
        $offset = ($page - 1) * $limit;

        $rooms = $query->offset($offset)
            ->limit($limit)
            ->get();

        $formattedRooms = $rooms->map(function ($room) use ($userId) {
            $otherUser = $room->orderer_id === $userId ? $room->picker : $room->orderer;

            $lastMessage = ChatMessage::where('chat_room_id', $room->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $unreadCount = ChatMessage::where('chat_room_id', $room->id)
                ->where('sender_id', '!=', $userId)
                ->where('is_read', false)
                ->count();

            return [
                'id' => $room->id,
                'order_id' => $room->order_id,
                'other_user' => [
                    'id' => $otherUser->id,
                    'full_name' => $otherUser->full_name,
                    'avatar_url' => $otherUser->avatar_url,
                ],
                'last_message' => $lastMessage?->content_original,
                'last_message_time' => $lastMessage?->created_at,
                'unread_count' => $unreadCount,
            ];
        });

        return [
            'data' => $formattedRooms->toArray(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ],
        ];
    }

    public function getMessages(string $roomId, int $page = 1, int $limit = 50): array
    {
        $query = ChatMessage::where('chat_room_id', $roomId)
            ->with(['sender'])
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $offset = ($page - 1) * $limit;

        $messages = $query->offset($offset)
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $formattedMessages = $messages->map(function ($message) {
            return [
                'id' => $message->id,
                'chat_room_id' => $message->chat_room_id,
                'sender_id' => $message->sender_id,
                'sender' => [
                    'id' => $message->sender->id,
                    'full_name' => $message->sender->full_name,
                    'avatar_url' => $message->sender->avatar_url,
                ],
                'content_original' => $message->content_original,
                'content_translated' => $message->content_translated,
                'translation_enabled' => $message->translation_enabled,
                'is_read' => $message->is_read,
                'created_at' => $message->created_at,
            ];
        });

        return [
            'data' => $formattedMessages->toArray(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ],
        ];
    }

    public function sendMessage(string $roomId, string $senderId, string $content, bool $forceTranslate = false): ChatMessage
    {
        $translatedContent = null;
        $room = ChatRoom::where('id', $roomId)->first();

        if ($room) {
            $receiverId = (strtolower($room->orderer_id) == strtolower($senderId)) ? $room->picker_id : $room->orderer_id;
            
            \Illuminate\Support\Facades\Log::info("Room: {$room->id}, Sender: {$senderId}, Receiver: {$receiverId}, Orderer: {$room->orderer_id}");
            
            $receiver = \App\Models\User::with(['ordererSettings', 'pickerSettings'])->find($receiverId);

            if ($receiver) {
                // Determine receiver's role in this room
                $isOrdererInRoom = (strtolower($receiverId) == strtolower($room->orderer_id));
                
                // Fetch settings for both roles to have full context
                $ordererSettings = $receiver->ordererSettings;
                $pickerSettings = $receiver->pickerSettings;

                // Primary check: role-specific settings based on their role in THIS room
                $settings = $isOrdererInRoom ? $ordererSettings : $pickerSettings;
                
                // Secondary check: If room-specific settings are missing or default, try the other role
                if (!$settings || ($settings->translation_language === 'English' && !$settings->auto_translate_messages)) {
                    $otherSettings = $isOrdererInRoom ? $pickerSettings : $ordererSettings;
                    if ($otherSettings && ($otherSettings->translation_language !== 'English' || $otherSettings->auto_translate_messages)) {
                        $settings = $otherSettings;
                    }
                }

                // Final check: If NO settings records exist or they are default, check the UserLanguage table
                $targetLanguageName = 'English';
                $autoTranslate = false;

                if ($settings) {
                    $targetLanguageName = $settings->translation_language;
                    $autoTranslate = $settings->auto_translate_messages;
                    \Illuminate\Support\Facades\Log::info("Settings found for receiver {$receiverId}: Language={$targetLanguageName}, AutoTranslate=" . ($autoTranslate ? 'YES' : 'NO'));
                } else {
                    // Pull from the profile's first language if settings aren't set up yet
                    $firstLang = \App\Models\UserLanguage::where('user_id', $receiverId)->first();
                    if ($firstLang) {
                        $targetLanguageName = $firstLang->language_name;
                        $autoTranslate = true; 
                        \Illuminate\Support\Facades\Log::info("Fallback to UserLanguage for receiver {$receiverId}: {$targetLanguageName}");
                    }
                }
                
                if ($forceTranslate || $autoTranslate) {
                    $targetCode = $this->getLanguageCode($targetLanguageName);
                    \Illuminate\Support\Facades\Log::info("Translating message for receiver {$receiverId} to code: {$targetCode}");
                    $translatedContent = $this->translateMessage($content, $targetCode);
                } else {
                    \Illuminate\Support\Facades\Log::info("Translation skipped for receiver {$receiverId} (autoTranslate=OFF)");
                }
            } else {
                \Illuminate\Support\Facades\Log::warning("Receiver User Not Found: {$receiverId}");
            }
        } else {
            \Illuminate\Support\Facades\Log::warning("Chat Room Not Found: {$roomId}");
        }

        $message = ChatMessage::create([
            'chat_room_id' => $roomId,
            'sender_id' => $senderId,
            'content_original' => $content,
            'content_translated' => $translatedContent,
            'translation_enabled' => !empty($translatedContent),
            'is_read' => false,
        ]);

        if ($room) {
            $room->touch();
        }

        return $message;
    }

    /**
     * Translates a message using a provider (MyMemory by default, AWS if configured).
     */
    private function translateMessage(string $content, string $targetLanguageCode): ?string
    {
        if (empty($content)) return null;

        $provider = env('TRANSLATION_PROVIDER', 'mymemory');
        \Illuminate\Support\Facades\Log::info("Translation provider: {$provider}");

        if (strtolower($provider) === 'aws') {
            try {
                $credentials = new \Aws\Credentials\Credentials(
                    env('AWS_ACCESS_KEY_ID'),
                    env('AWS_SECRET_ACCESS_KEY')
                );

                $client = new \Aws\Translate\TranslateClient([
                    'version' => 'latest',
                    'region'  => env('AWS_DEFAULT_REGION', 'us-east-1'),
                    'credentials' => $credentials,
                    'http' => [
                        'verify' => false // Disable SSL verification (only for development)
                    ]
                ]);

                $result = $client->translateText([
                    'Text' => $content,
                    'SourceLanguageCode' => 'auto',
                    'TargetLanguageCode' => $targetLanguageCode
                ]);

                $translated = $result['TranslatedText'] ?? null;

                if ($translated) {
                    \Illuminate\Support\Facades\Log::info("AWS Translate Success: " . $translated);
                    return $translated;
                }

                \Illuminate\Support\Facades\Log::warning("AWS Translate returned empty result");
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('AWS Translate failed: ' . $e->getMessage());
            }

            return null;
        }

        // Default: MyMemory free translation (no API key needed)
        try {
            $response = \Illuminate\Support\Facades\Http::get('https://api.mymemory.translated.net/get', [
                'q' => $content,
                'langpair' => "Autodetect|{$targetLanguageCode}"
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['responseData']['translatedText'])) {
                    $translated = $data['responseData']['translatedText'];

                    // MyMemory sometimes returns an error message as translated text (still 200 OK)
                    if (strpos(strtoupper($translated), 'PLEASE SELECT TWO') !== false || 
                        strpos(strtoupper($translated), 'INVALID TARGET') !== false) {
                        \Illuminate\Support\Facades\Log::warning("MyMemory Warning in translatedText: " . $translated);

                        // The API returns this when the source and target languages are the same (or target is invalid).
                        // In that case, treat it as "no translation needed" rather than a failure.
                        return $content;
                    }

                    \Illuminate\Support\Facades\Log::info("MyMemory Success: " . $translated);
                    return html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }

            \Illuminate\Support\Facades\Log::warning("MyMemory API failed or returned unexpected structure: " . $response->body());
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('MyMemory translation failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Maps language names to ISO language codes.
     */
    private function getLanguageCode(string $languageName): string
    {
        // If it's already a 2-character code, just return it
        if (strlen(trim($languageName)) === 2) {
            return strtolower(trim($languageName));
        }

        $map = [
            'english' => 'en',
            'spanish' => 'es',
            'french' => 'fr',
            'german' => 'de',
            'italian' => 'it',
            'portuguese' => 'pt',
            'dutch' => 'nl',
            'polish' => 'pl',
            'russian' => 'ru',
            'chinese' => 'zh',
            'japanese' => 'ja',
            'korean' => 'ko',
            'arabic' => 'ar',
            'turkish' => 'tr',
            'hindi' => 'hi',
            'bengali' => 'bn',
            'urdu' => 'ur',
            'indonesian' => 'id',
            'thai' => 'th',
            'vietnamese' => 'vi',
            'hebrew' => 'he',
            'greek' => 'el',
            'romanian' => 'ro',
            'hungarian' => 'hu',
            'czech' => 'cs',
            'swedish' => 'sv',
            'danish' => 'da',
            'finnish' => 'fi',
            'norwegian' => 'no',
        ];

        // Clean input: remove parentheses and extra spaces, lowercase
        $cleanName = strtolower(trim(preg_replace('/\s*\(.*?\)/', '', $languageName)));
        
        return $map[$cleanName] ?? 'en';
    }

    public function translateExistingMessage(string $messageId, string $targetLanguageCode): ?string
    {
        $message = ChatMessage::find($messageId);
        if (!$message) return null;

        // Allow calling code to pass either a language name (e.g. "Spanish") or an ISO code (e.g. "es").
        $targetCode = $this->getLanguageCode($targetLanguageCode);

        $translated = $this->translateMessage($message->content_original, $targetCode);
        if ($translated !== null) {
            $enableTranslation = $translated !== $message->content_original;
            $message->update([
                'content_translated' => $translated,
                'translation_enabled' => $enableTranslation,
            ]);
            return $translated;
        }

        return null;
    }

    public function markMessageAsRead(string $messageId): ChatMessage
    {
        $message = ChatMessage::find($messageId);
        if ($message) {
            $message->update(['is_read' => true]);
        }
        return $message;
    }

    public function markRoomAsRead(string $roomId, string $userId): void
    {
        ChatMessage::where('chat_room_id', $roomId)
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }
}
