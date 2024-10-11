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
        $callbackData = $query['data'];
        $chatId = $query['message']['chat']['id'];
        $url = $this->url . "/sendMessage";
        $text = "";
        $keyboard = "";

        Log::info($callbackData);

        switch($callbackData){
            case 'main_menu':
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
                            ['text' => '📋 Инструкции', 'callback_data' => 'instructions'],
                        ],
                        [
                            ['text' => '📄 Новости', 'callback_data' => 'news']
                        ]
                    ]
                ];
                break;
            case 'start_search':
                $text = "Выберите необходимый режим:
➕ Поиск слотов — быстрый способ найти свободные слоты по указанным параметрам
⭐ Автобронирование — поиск и автоматическое бронирование вашей поставки на желаемую дату";

                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => 'Поиск слотов', 'callback_data' => 'search_slots']],
                        [['text' => 'Автобронирование', 'callback_data' => 'auto_booking']],
                        [['text' => 'Назад', 'callback_data' => 'main_menu']]
                    ]
                ];
                break;
            case 'my_searches':
                $text = "Выберите пункт в меню";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Активные поставки ✅', 'callback_data' => 'active_supplies'],
                            ['text' => 'Неактивные поставки ❌', 'callback_data' => 'inactive_deliveries']
                        ]
                    ]
                ];
                break;
            case 'active_supplies':
                $text = "Еще не готово";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Назад', 'callback_data' => 'my_searches'],
                            ['text' => 'В главное меню', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
                break;
            case 'inactive_deliveries':
                $text = "Скоро будет готово";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Назад', 'callback_data' => 'my_searches'],
                            ['text' => 'В главное меню', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
                break;
            case 'statistics':
                $text = "В данном разделе представлена статистика поисков бота для ТОП-15 популярных для отслеживания складов Wildberries.

Значения процентов расчитываются как соотношение найденных слотов ко всем созданным отслеживаниям.
Благодаря данной статистике вы можете примерно оценить свои шансы в текущий момент найти слот на интересующий склад (Выше процент -> Больше шансов найти).

Тут будет список!
";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'В главное меню', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
                break;

            case 'subscribe':
                $text = "Подписка еще не готова";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Тарифы', 'callback_data' => 'tariffs']
                        ],
                        [
                            ['text' => 'Автобронирования', 'callback_data' => 'autobooking']
                        ],
                        [
                            ['text' => 'Назад', 'callback_data' => 'main_menu']
                        ],
                    ]
                ];
                break;

            case 'delivery':
                $text = '🔍 Выберите тип поиска';
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => 'По поисковому запросу', 'callback_data' => 'search_query']],
                        [['text' => 'По категории', 'callback_data' => 'search_category']],
                        [['text' => 'Назад', 'callback_data' => 'main_menu']]
                    ]
                ];

                break;
            case 'search_query':
                $text = '🔍 Введите поисковый запрос, по которому нужна информация о приоритетных складах';
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => 'Главное меню', 'callback_data' => 'main_menu']]
                    ]
                ];
                break;
            case'search_category':
                $text = "🔍 Вставьте ссылку на категорию Wildberries";
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => 'Главное меню', 'callback_data' => 'main_menu']]
                    ]
                ];
                break;
            case 'support_me':
                $text = "Здравствуйте.
Добро пожаловать в раздел обратной связи бота лимитов.

Что-то не работает?
Напишите максимально подробно суть проблемы.
Чем больше деталей - тем проще и быстрее найти и устранить неисправность.

У вас есть предложения?
Присылайте ваши идеи по улучшениям и дополнениям функционала бота.

Хотите обсудить сотрудничество, рекламу или что-то ещё?
Пишите и я отвечу вам при первой же возможности.";

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Написать', 'callback_data' => 'write'],
                        ],
                        [
                            ['text' => 'Адреса складов', 'callback_data' => 'address']
                        ],
                        [
                            ['text' => 'О боте', 'callback_data' => 'about'],
                        ],
                        [
                            ['text' => 'Главное меню', 'callback_data' => 'main_menu'],
                        ]
                    ]
                ];
                break;
            case 'write':
                $text = "Еще не готово";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Назад', 'callback_data' => 'support_me'],
                        ]
                    ]
                ];
                break;
            case 'address':
                $text = "Список адресов:";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Главное меню', 'callback_data' => 'main_menu'],
                        ]
                    ]
                ];
                break;
            case 'about':
                $text = 'Добрый день! Наша команда создала и развивает бота для помощи поиска лимитов на российских маркетплейсах. Мы ценим ваше время и хотим сделать эту задачу максимально простой и быстрой.';
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Главное меню', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
                break;
            case 'instructions':
                $text = "https://instuctions.ru";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Главное меню', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
                break;
            case 'news':
                $text = "https://news.ru";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Главное меню', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
                break;
            default:
                Log::info('Неверная команда');
                $text = "Указана неверная команда";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Главное меню', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
        }

        Http::post($url, [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($keyboard),
            'parse_mode' => 'Markdown'
        ]);
    }
}
