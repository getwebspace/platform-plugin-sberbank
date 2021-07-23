Sberbank для WebSpace Engine
====
######(Плагин)

Плагин добавляет возможность производить оплату товаров через Sberbank.

#### Установка
Поместить в папку `plugin` и подключить в `index.php` добавив строку:
```php
// sberbank plugin
$plugins->register(new \Plugin\Sberbank\SberbankPlugin($container));
```

#### License
Licensed under the MIT license. See [License File](LICENSE.md) for more information.
