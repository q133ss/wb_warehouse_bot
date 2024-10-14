<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function loadWarehouses($page = 1)
    {
        // Попробуем получить данные о складах из кэша
        $warehouses = Cache::remember('warehouses_data', 24 * 60 * 60, function () {
            $response = Http::withHeaders([
                'Authorization' => env('WB_KEY'),
            ])->get('https://supplies-api.wildberries.ru/api/v1/warehouses');

            if ($response->successful()) {
                return $response->json(); // Возвращаем данные в случае успешного запроса
            } else {
                Log::error('Ошибка загрузки складов', $response->body());
                return [
                    'inline_keyboard' => ['text' => 'Главное меню', 'callback_data' => 'main_menu']
                ];
            }
        });

        // Устанавливаем количество элементов на странице
        $perPage = 10;
        // Рассчитываем, с какого элемента начинать
        $offset = ($page - 1) * $perPage;
        // Извлекаем нужные 10 элементов для этой страницы
        $warehousesByPage = array_slice($warehouses, $offset, $perPage);

        $keyboard = [
            'inline_keyboard' => []
        ];

        // Проходим по каждому складу и создаем кнопку
        foreach ($warehousesByPage as $warehouse) {
            $keyboard['inline_keyboard'][] = [
                ['text' => $warehouse['name'], 'callback_data' => 'selectWarehouse_' . $warehouse['ID']]
            ];
        }

        // Добавляем кнопки навигации и главное меню
        $keyboard['inline_keyboard'][] = [
            ['text' => '<', 'callback_data' => 'prevPage_' . ($page - 1)],
            ['text' => '>', 'callback_data' => 'nextPage_' . ($page + 1)]
        ];

        // Кнопка для возврата в главное меню
        $keyboard['inline_keyboard'][] = [
            ['text' => 'Главное меню', 'callback_data' => 'main_menu']
        ];

        return $keyboard;
    }
    public function boxType($callbackData, $userId){
        // Извлекаем тип (box, mono или super) и warehouseId из callback_data
        preg_match('/(box|mono|super)_(\d+)/', $callbackData, $matches);

        if (isset($matches[1]) && isset($matches[2])) {
            $type = $matches[1]; // Тип (box, mono или super)
            $warehouseId = (int)$matches[2]; // ID склада

            // Логика обработки в зависимости от типа
            $text = 'Выберите тип приёмки, на который будем искать слот

При выборе платной приемки бот будет искать указанный коэффициент или ниже

Например: Выбрано "До x2" - бот ищет: бесплатную, x1 и x2 приемки';

            $typeName = '';
            switch ($type) {
                case 'box':
                    $typeName = 'Короба';
                    break;

                case 'mono':
                    $typeName = 'Монопаллеты';
                    break;

                case 'super':
                    $typeName = 'Суперсейф';
                    break;

                default:
                    break;
            }

            $record = DB::table('user_warehouse')
                ->insertGetId([
                    'user_id' => $userId,
                    'warehouse_id' => $warehouseId,
                    'type' => $typeName
                ]);

            Log::info('RECORD: '. $record);

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Только бесплатная', 'callback_data' => $record.'_accept_0']
                    ],

                    [
                        ['text' => 'До x1', 'callback_data' => $record.'_accept_1'],
                        ['text' => 'До x2', 'callback_data' => $record.'_accept_2'],
                        ['text' => 'До x3', 'callback_data' => $record.'_accept_3'],
                    ],

                    [
                        ['text' => 'До x4', 'callback_data' => $record.'_accept_4'],
                        ['text' => 'До x5', 'callback_data' => $record.'_accept_5'],
                        ['text' => 'До x6', 'callback_data' => $record.'_accept_6'],
                    ],

                    [
                        ['text' => 'До x7', 'callback_data' => $record.'_accept_7'],
                        ['text' => 'До x8', 'callback_data' => $record.'_accept_8'],
                        ['text' => 'До x9', 'callback_data' => $record.'_accept_9'],
                    ],
                    [
                        ['text' => 'До x10', 'callback_data' => $record.'_accept_10']
                    ],
                ]
            ];
        } else {
            $text = "Ошибка при обработке выбора.";
        }

        return [
            'text' => $text,
            'keyboard' => $keyboard
        ];
    }

    public function limits($callbackData)
    {
        // Извлекаем ID и тип акцепта из callback_data
        preg_match('/(\d+)_accept_(\d+)/', $callbackData, $matches);

        if (isset($matches[1]) && isset($matches[2])) {
            $recordId = (int)$matches[1];
            $acceptType = (int)$matches[2];
            DB::table('user_warehouse')
                ->where('id', $recordId)
                ->update(['accept' => $acceptType]);
        }

        $text = "Выберите даты, когда вам нужны лимиты";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Сегодня', 'callback_data' => $recordId.'_limit_today'],
                ],
                [
                    ['text' => 'Завтра', 'callback_data' => $recordId.'_limit_tomorrow'],
                ],
                [
                    ['text' => 'Неделя', 'callback_data' => $recordId.'_limit_week'],
                ],
                [
                    ['text' => 'Искать, пока не найдется', 'callback_data' => $recordId.'_limit_none'],
                ],
                [
                    ['text' => 'Главное меню', 'callback_data' => 'main_menu'],
                ]
            ]
        ];
        return [
            'text' => $text,
            'keyboard' => $keyboard
        ];
    }

    public function limitSet($callbackData)
    {
        // Извлекаем ID записи и тип лимита
        preg_match('/(\d+)_limit_(\w+)/', $callbackData, $matches);

        if (isset($matches[1]) && isset($matches[2])) {
            $recordId = (int)$matches[1]; // ID записи
            $limitType = $matches[2]; // Тип лимита (today, tomorrow, week, none)

            $record = DB::table('user_warehouse')
                ->where('id', $recordId);

            $text = "Для использования данной функции необходима подписка ( Но в данном случае все сработает и без нее)";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Оплатить', 'callback_data' => 'main_menu']
                    ],
                    [
                        ['text' => 'В главное меню', 'callback_data' => 'main_menu']
                    ]
                ]
            ];

            switch ($limitType) {
                case 'today':
                    $record->update([
                        'date' => now()
                    ]);
                    break;

                case 'tomorrow':
                    $record->update([
                        'date' => now()->addDay()
                    ]);
                    break;

                case 'week':
                    $record->update([
                        'date' => now()->addWeek()
                    ]);
                    break;

                case 'none':
                    $record->update([
                        'date' => now()->addYears(100)
                    ]);
                    break;

                default:
                    $text = "Неизвестный тип лимита.";
                    Log::error('Unknown limit type: ' . $limitType);
                    break;
            }
        }

        return [
            'text' => $text,
            'keyboard' => $keyboard
        ];
    }

    public function selectBox($callbackData, $userId)
    {
        // Извлекаем ID склада из callback_data
        preg_match('/selectWarehouse_(\d+)/', $callbackData, $matches);

        if (isset($matches[1])) {
            $warehouseId = (int)$matches[1]; // ID выбранного склада
            Log::info('WHID. '. $warehouseId);

            // Проверяем существование записи в таблице user_warehouse
            $exists = DB::table('user_warehouse')
                ->where('user_id', $userId)
                ->where('warehouse_id', $warehouseId)
                ->exists();

            if ($exists) {
                // Если запись существует, удаляем её
                DB::table('user_warehouse')
                    ->where('user_id', $userId)
                    ->where('warehouse_id', $warehouseId)
                    ->delete();
            }
            $text = "Выберите пункт в меню";
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => 'Короб', 'callback_data' => 'box_'.$warehouseId]],
                    [['text' => 'Монопаллета', 'callback_data' => 'mono_'.$warehouseId]],
                    [['text' => 'Суперсейф', 'callback_data' => 'super_'.$warehouseId]],
                    [['text' => 'Выбрать другой склад', 'callback_data' => 'search_wb']],
                ]
            ];
        }

        return [
            'text' => $text,
            'keyboard' => $keyboard
        ];
    }

    public function warehouseDisplay($callbackData)
    {
        // Извлекаем номер страницы из callback_data
        preg_match('/(prevPage_|nextPage_)(\d+)/', $callbackData, $matches);
        if (isset($matches[2])) {
            $page = (int)$matches[2]; // Текущая страница
        }

        // Проверяем, что страница больше 0 (чтобы не уйти в отрицательные)
        if ($page < 1) {
            $page = 1;
        }
        // Вызываем метод для обновления клавиатуры
        $text = "Выберите склад";
        $keyboard = $this->loadWarehouses($page);

        return [
            'text' => $text,
            'keyboard' => $keyboard
        ];
    }

    public function texts($callbackData)
    {
        switch ($callbackData) {
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
                            ['text' => 'Автобронирования', 'callback_data' => 'auto_booking']
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
            case 'auto_booking':
                $text = "⚡️ Чтобы воспользоваться автоматическим бронированием на Wildberries, сначала создайте черновик поставки в личном кабинете, добавьте его в бот и укажите предпочтительные даты.

Важно❕ Бот не обходит ограничения Wildberries. Если маркетплейс отклоняет поставку по своим причинам, бот предложит выбрать другую дату или склад. Введите номер телефона для входа в личный кабинет WB.

Формат: +7**********

⚠️ Ожидание смс-кода может занять некоторое время!";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Главное меню', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
                break;
            case 'search_slots':
                $text = 'Выберите пункт в меню';
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Wildberries', 'callback_data' => 'search_wb'],
                            ['text' => 'Ozon', 'callback_data' => 'search_oz']
                        ]
                    ]
                ];
                break;
            case 'search_wb':
                $text = "Выберите склад";
                $keyboard = $this->loadWarehouses();
                break;
            case 'search_oz':
                $text = "Поиск Озон";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Главное меню', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
                break;
            default:
                Log::info('Неверная команда: ' . $callbackData);
                $text = "Указана неверная команда";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => 'Главное меню', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
        }

        return [
            'text' => $text,
            'keyboard' => $keyboard
        ];
    }
}
