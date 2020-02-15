## Ostiwe Saver

[![Packagist Version](https://img.shields.io/packagist/v/ostiwe/vksaver?style=flat-square)](https://packagist.org/packages/ostiwe/vksaver)

### Описание
Данный пакет предназначен для владельцев или редакторов сообществ в
социальной сети ВКонтакте.
Благодаря данному пакету, вы сможете добавлять контент в ваше сообщество
без лишних действий.

В данный момент поддерживается добавление в отложку картинок.

#### Как это работает?
##### Если вы ищите контент внутри ВКонтакте
Если вам понравилась какая-нибудь картинка из новостной ленты/группы, то
вам нужно всего лишь отправить пост с этой картинкой вашему боту, либо же
просто картинку.

Если в посте больше одной картинки, то вы можете лайкнуть ту, которая вам
понравилась, и которую вы хотели бы добавить в отложку. Либо, вы можете
отослать этот пост с картинками боту, и он добавит в отложку все картинки
которые были в посте.

##### Если вы ищите контент в интернете
Если вам понравилась картинка в интернете, то для отправки её в сообщество
вы можете использовать расширение (о нём позже).

Просто нажмите правой кнопкой мыши на картинку, и в контекстном меню
выберите сообщество, в которое вы хотите отправить выбранное изображение.

#### Что нужно для работы?

Для работы необходимо будет дополнительно установить официальную библиотеку
от ВКонтатке.

Так же необходимо будет установить в свой браузер расширение, ссылка на
него будет в конце.

#### Использование

Для начала получите токен пользователя со следующими правами:
* Доступ к фотографиям
* Доступ к стене
* Доступ в любое время
* Доступ к группам

Теперь устанавливаем пакет следующей командой:
```shell
composer require ostiwe/vksaver
```


Далее создаем объект UserClient используя следующий код:

``$ostiwe = new \Ostiwe\Client\UserClient('User id', 'User access Token',[]');``

Первым параметром идет ID пользователя, который является администратором/редактором
сообщества.

Вторым параметром идет API токен пользователя с необходимыми правами,
которые были описаны выше.

Последним параметром является ассоциативным массивом, в котором описываются
настройки для сообществ. Данный массив может выглядить следующим способом:

```php
[
    162768498 => [ // В качестве ключа используется ID сообщества
        'post_interval' => 2, // Интервал между постами (в часах)
        'liked_only' => true, // Если в посте несколько картинок, брать те, что с лайком
        'confirmation_code' => '91sj188x', // Код подтверждения сервера
        'secret' => 'super_puper1secret0code', // Секретный ключ Callback сервера
        'access_token' => '31xz6bb054765b2c4d52472c5dc615ea7b2ds88e9', // API токен сообщества (подробнее ниже)
        'name' => 'Pubj' // Короткое имя для класса-обработчика
    ],
    //...
];
```

Для работы необходим API ключ сообщества со следующими правами:
* Сообщения сообщества
* Фотографии
* Стена

Так же убедитесь, что в вашем сообществе включены сообщения (т.е можно
писать сообществу).

Далее вызываем метод ``callbackHandler`` у объекта ``$ostiwe`` и в качестве
параметра указываем декодированный JSON объект, который нам присылает
Callback API:

```php
$data = json_decode(file_get_contents('php://input'), true);
$ostiwe->callbackHandler($data);
```

### Добавление обработчиков для сообществ
Создайте каталог, в котором вы будете хранить классы-обработчики для
сообществ. Например Classes.

Далее, перед вызовом метода ``callbackHandler()`` вызовите метод
``setHandlersPatch()``. В параметре передайте путь до папки, например:
```php
$ostiwe->setHandlersPatch(__DIR__ . '/Classes');
```

В данной папке создайте новый класс с именем, которое вы придумали, когда
описывали массив с параметрами сообщества.
``'name' => 'Pubj' // Короткое имя для класса-обработчика``

После этого имени добавьте ``Handler``. Должно получить на подобии этого:
``NameHandler.php``.

Теперь просто создайте класс с унаследованием от класса ``PubHandler``

```php
class NameHandler extends \Ostiwe\Handlers\PubHandler {}
```
или
```php
use Ostiwe\Handlers\PubHandler;
class NameHandler extends PubHandler {}
```

Теперь осталось указать адрес сервера, который будет принимать и обрабатывать
все события. Делается это в настройках сообщества.


### Расширение для браузера

В дополнение к данному пакету есть расширение ссылка на него будет в
конце этого раздела.

Для того, чтобы использовать расширение, необходимо добавить в массив
с группами следующий элемент:
```php
 'plugin' => [
        'secret' => 'super_puper1secret0code',
    ]
```
В место ``super_puper1secret0code`` придумайте или сгенерируйте ключ
состоящий из случайных цифр и букв (на англ.).

[Далее смотрите в репозитории с расширением.](https://github.com/ostiwe/vksaver-chrome)


### Пример

Для index.php
```php
$requestData = json_decode(file_get_contents('php://input'), true);
$groups = [
    153626005 => [
        'post_interval' => 2, // hours
        'liked_only' => true,
        'confirmation_code' => 'confirmation code for server',
        'secret' => 'superSecret918s_ds',
        'access_token' => 'group access token',
        'name' => 'AnyName'
    ],
    'plugin' => [
        'secret' => 'superSecret918s_ds',
    ],
];
$ostiwe = new \Ostiwe\Client\UserClient('you vk id', 'you profile token', $groups);

$ostiwe->setHandlersPatch(__DIR__ . '/Classes');

try {
    $ostiwe->callbackHandler($requestData);
} catch (Exception $e) {
    // do something...
}
```

Для /path/to/Classes/AnyNameHandler.php
```php
use Ostiwe\Handlers\PubHandler;

class AnyNameHandler extends PubHandler
{
    public function __construct($userToken, $userId, $pubToken, $pubId)
    {
        parent::__construct($userToken, $userId, $pubToken, $pubId);
    }

    /**
     * @param array $attachmentsList
     * @param array $pubParams
     * @param string $postText
     * @throws Exception
     */
    public function handle(array $attachmentsList, array $pubParams, string $postText = '')
    {
        parent::handle($attachmentsList, $pubParams, $postText); // TODO: Change the autogenerated stub
    }

    /**
     * @param array $pubParams
     * @param array $attachmentsList
     * @param string $postText
     * @return array
     * @throws Exception
     */
    public function post(array $pubParams, array $attachmentsList, $postText = '')
    {
        return parent::post($pubParams, $attachmentsList, $postText); // TODO: Change the autogenerated stub
    }

    /**
     * @param string $message
     * @param array $attachments
     * @throws Exception
     */
    public function sendNotificationMessage(string $message, array $attachments = [])
    {
        parent::sendNotificationMessage($message, $attachments); // TODO: Change the autogenerated stub
    }

}
```


По умолчанию, вам в личное сообщение присылается только сообщение об
успешном добавлении поста в отложку:

```php
public function handle(array $attachmentsList, array $pubParams, string $postText = '')
    {
        try {
            $postInfo = $this->post($pubParams, $attachmentsList,$postText);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), null, $e);
        }
        $postDate = date('d.m.Y в H:i', $postInfo['date']);
        $textMessage = "Пост будет опубликован $postDate <br>";
        $textMessage .= "vk.com/wall-{$this->pubId}_{$postInfo['post_id']}";

        try {
            $this->sendNotificationMessage($textMessage);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), null, $e);
        }
    }
```

Если вы хотите отправить сообщение перед добавлением поста в отложку, то
вы можете поступить следующим образом:

```php
// Пример из рабочего скрипта
 public function handle(array $attachmentsList, array $pubParams, string $postText = '')
    {
        parent::sendNotificationMessage("Начинаю закидывать выбранные вами картинки в отложку", $attachmentsList);
        parent::handle($attachmentsList, $pubParams, $postText); // TODO: Change the autogenerated stub
    }
```

Если вы хотите выполнить другие действия, то вы можете переписать данный
метод
```php
 public function handle(array $attachmentsList, array $pubParams, string $postText = '')
    {
        // Ваш код...
    }
```