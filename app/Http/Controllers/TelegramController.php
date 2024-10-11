<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
                    ['text' => 'Начать', 'callback_data' => 'start_search'],
                    ['text' => 'Мои поиски', 'callback_data' => 'my_searches'],
                    ['text' => 'Статистика', 'callback_data' => 'statistics']
                ]
            ]
        ];

        Log::info('ID:'.$chatId);

        Http::post($url, [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'Markdown'
        ]);
    }

    // Метод для отправки простого сообщения
    private function sendMessage($chatId, $text)
    {
        $url = $this->url . "/sendMessage";

        Http::post($url, [
            'chat_id' => $chatId,
            'text' => $text
        ]);
    }

    private function processCallbackQuery($query)
    {
        $chatId = $query['message']['chat']['id'];
        Log::info("ChatID: ".$chatId);
        // Делаем сенд месседж или что-то еще там
    }
}
