<?php
// Интерфейс сервиса настроек пользователя
interface UserSettingsService {
    //внешняя функция которая запишет новые настройки в базу
    public function changeSetting($userId, $settingName, $settingValue);

    //внешняя функция которая сгенерирует код и сохранит его в базу
    public function generateConfirmationCode($userId, $method);
}

// Интерфейс сервиса подтверждения
interface ConfirmationService {
    //Функция отправки кода
    public function sendConfirmationCode($userId, $code, $method);

    //Функция проверки кода
    public function verifyConfirmationCode($userId, $code);
}

// Интерфейс репозитория пользователей
interface UserRepository {
    public function getUserSettings($userId);
    public function saveUserSetting($userId, $settingName, $settingValue);
    public function saveConfirmationCode($userId, $code, $method);
}

//абстрактный кэш в качестве хранения промежуточных значений
function cache($data, $name, $ttl): array{ return $data;};


// Контроллер для обработки запросов пользователя
class UserController {
    protected $settingsService;
    protected $confirmationService;
    protected $userRepository;

    public function __construct(UserSettingsService $settingsService, ConfirmationService $confirmationService, UserRepository $userRepository) {
        $this->settingsService = $settingsService;
        $this->confirmationService = $confirmationService;
        $this->userRepository = $userRepository;
    }

    //Функция изменения настроек пользователя
    public function changeSetting($userId, $method, $settingName, $settingValue): string{
        // Изменение настройки пользователя с генерацией кода подтверждения
        $code = $this->settingsService->generateConfirmationCode($userId, $method);

        //отправка кода подтверждения и запись настроек при успехе
        if ($this->confirmationService->sendConfirmationCode($userId, $code, $method)){
            cache([$settingName, $settingValue], $userId.$code.$method, 900);
            return json_encode(['status'=>true, 'message'=>'Код успешно отправлен']);
        } else return json_encode(['status'=>false, 'message'=>'Не удалось отправить код']);
    }

    //Функция подтверждения надстроек пользователя
    public function confirmSettingChange($userId, $code): string{
        // Проверка кода подтверждения и применение изменений
        $data = $this->confirmationService->verifyConfirmationCode($userId, $code);
        if ($data->status) {
            $this->settingsService->changeSetting($userId, $data->settingName, $data->settingValue);
            return json_encode(['status'=>true, 'message'=>'Изменения настроек подтверждены']);
        } else {
            return json_encode(['status'=>false, 'message'=>'Неверный код подтверждения']);
        }
    }
}

// Пример использования контроллера
$userController = new UserController($userSettingsService, $confirmationService, $userRepository);
$userController->changeSetting($userId, "telegram", "new@example.com", "notification_email");
$userController->confirmSettingChange($userId, $code);
