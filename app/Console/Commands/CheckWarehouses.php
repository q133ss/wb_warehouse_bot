<?php

namespace App\Console\Commands;

use App\Http\Controllers\TelegramController;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckWarehouses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:warehouses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверяет склады';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Получаем все уникальные warehouse_id из user_warehouse
        $uniqueWarehouses = DB::table('user_warehouse')->distinct()->pluck('warehouse_id');

        // 2. Отправляем запрос на получение коэффициентов
        $response = Http::withHeaders([
            'Authorization' => env('WB_KEY'), // Замените на ваш ключ
        ])->get('https://supplies-api.wildberries.ru/api/v1/acceptance/coefficients');

        if ($response->successful()) {
            $this->info('Запрос коэффициентов прошел успешно');
            $coefficientsData = $response->json();
            // 3. Проходим по каждой записи и ищем совпадения
            foreach ($coefficientsData as $coefficientData) {
                $warehouseID = $coefficientData['warehouseID'];
                $coefficient = $coefficientData['coefficient'];
                $boxTypeName = $coefficientData['boxTypeName'];
                $this->info("Проверяю склад {$warehouseID} - {$boxTypeName}");
                // Проверяем, есть ли warehouse_id в уникальных значениях
                if ($uniqueWarehouses->contains($warehouseID)) {
                    // Находим соответствующую запись в user_warehouse
                    $userWarehouses = DB::table('user_warehouse')
                        ->where('is_notified', 0)
                        ->where('warehouse_id', $warehouseID)
                        ->where('type', $boxTypeName)
                        ->where('accept', '<=', $coefficient) // Проверяем, чтобы accept был меньше или равен coefficient
                        ->get();

                    // 4. Если есть совпадения, отправляем сообщение пользователям
                    foreach ($userWarehouses as $userWarehouse) {
                        $usrId = $userWarehouse->user_id;
                        $chatId = User::find($usrId)->chat_id;
                        $message = "Найден склад!\n" .
                            "Название: {$coefficientData['warehouseName']}\n" .
                            "Коэффициент: {$coefficient}";

                        $this->info("Отправляю сообщение");
                        // Отправляем сообщение
                        (new TelegramController())->testSend($chatId, $message);

                        DB::table('user_warehouse')
                            ->where('id', $userWarehouse->id) // Ищем запись по ID
                            ->update([
                                'is_notified' => 1
                            ]);
                        sleep(1);
                    }
                }
            }
        } else {
            $this->error('При запросе коэффициентов произошла ошибка');
            Log::error('Ошибка при получении коэффициентов: ' . $response->body());
        }
    }
}
