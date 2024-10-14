<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TelegramController extends Controller
{
    protected string $telegram_api_url = "https://api.telegram.org/bot";
    protected string $telegram_token;
    protected string $url;

    public function __construct()
    {
        $this->telegram_token = env('TELEGRAM_BOT_TOKEN');
        $this->url = $this->telegram_api_url . $this->telegram_token;
    }

    public function handle(Request $request)
    {
        $update = $request->all();

        if (isset($update['message'])) {
            $this->processMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->processCallbackQuery($update['callback_query']);
        }

        return response()->json(['status' => 'ok']);
    }

    // Обработка сообщений
    private function processMessage($message)
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $username = $message['from']['username'] ?? 'Без ника';

        Cache::remember("user_{$chatId}", 30 * 24 * 60 * 60, function() use ($chatId, $username) {
            return User::firstOrCreate(
                ['chat_id' => $chatId],
                ['name' => $username]
            );
        });

        // Далее можно обработать команды, например /start
        if ($text === '/start') {
            $this->sendMenu($chatId); // Показать основное меню с кнопками
        } else {
            $this->sendMessage($chatId, "Неизвестная команда. Используйте /start.");
        }
    }

    private function sendMenu($chatId)
    {
        $url = $this->url . "/sendMessage";

        $text = "⭐ Я умею автоматически находить и бронировать найденные слоты на складах Wildberries и Ozon.\n\n" .
            "🟪 На Wildberries я умею находить слоты с бесплатной приёмкой. Или с платной, до подходящего вам платного коэффициента.\n\n" .
            "Выберите пункт в меню.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🚀 Начать', 'callback_data' => 'start_search'],
                ],
                [
                    ['text' => '🔎 Мои поиски', 'callback_data' => 'my_searches'],
                    ['text' => '📈 Статистика', 'callback_data' => 'statistics']
                ],
                [
                    ['text' => '💎 Подписка', 'callback_data' => 'subscribe'],
                ],
                [
                    ['text' => '🚚 Скорость доставки складов', 'callback_data' => 'delivery'],
                ],
                [
                    ['text' => '🚨 Поддержка', 'callback_data' => 'support_me'],
                    ['text' => '📋 Инструкции', 'callback_data' => 'instructions']
                ],
                [
                    ['text' => '📄 Новости', 'callback_data' => 'news']
                ]
            ]
        ];

        Http::post($url, [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'Markdown'
        ]);
    }

    // Метод для отправки простого сообщения
    public function sendMessage($chatId, $text)
    {
        $url = $this->url . "/sendMessage";

        Http::post($url, [
            'chat_id' => $chatId,
            'text' => $text
        ]);
    }

    private function processCallbackQuery($query)
    {
        $callbackData = $query['data'];
        $chatId = $query['message']['chat']['id'];
        $url = $this->url . "/sendMessage";
        $text = "";
        $keyboard = "";
        $userId = DB::table('users')
            ->where('chat_id', $chatId)
            ->value('id');

        // Проверяем, что в callback_data есть "box_", "mono_" или "super_"
        if (strpos($callbackData, 'box_') !== false || strpos($callbackData, 'mono_') !== false || strpos($callbackData, 'super_') !== false) {

            $service = (new TelegramService())->boxType($callbackData, $userId);

        } elseif (strpos($callbackData, '_accept_') !== false) {

            $service = (new TelegramService())->limits($callbackData);

        } elseif (strpos($callbackData, '_limit_') !== false) {

            $service = (new TelegramService())->limitSet($callbackData);

        }elseif (strpos($callbackData, 'selectWarehouse_') !== false) {

            $service = (new TelegramService())->selectBox($callbackData, $userId);

        } elseif (strpos($callbackData, 'prevPage_') !== false || strpos($callbackData, 'nextPage_') !== false) {
            $service = (new TelegramService())->warehouseDisplay($callbackData);
        } else {
            $service = (new TelegramService())->texts($callbackData);
        }

        $text = $service['text'];
        $keyboard = $service['keyboard'];

        Http::post($url, [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'Markdown'
        ]);
    }

    public function testSend($chatId, $text)
    {
        $url = $this->url . "/sendMessage";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'В главное меню', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        Http::post($url, [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'Markdown'
        ]);
    }
}
