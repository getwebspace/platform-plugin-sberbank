<?php declare(strict_types=1);

namespace Plugin\Sberbank;

use App\Domain\AbstractPlugin;
use Psr\Container\ContainerInterface;

class SberbankPlugin extends AbstractPlugin
{
    const NAME = 'SberbankPlugin';
    const TITLE = 'Сбербанк эквайринг';
    const DESCRIPTION = 'Возможность принимать безналичную оплату товаров и услуг';
    const AUTHOR = 'Aleksey Ilyin';
    const AUTHOR_SITE = 'https://getwebspace.org';
    const VERSION = '1.0';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->addSettingsField([
            'label' => 'Username',
            'type' => 'text',
            'name' => 'username',
        ]);
        $this->addSettingsField([
            'label' => 'Password',
            'type' => 'text',
            'name' => 'password',
        ]);
        $this->addSettingsField([
            'label' => 'Description',
            'description' => 'В указанной строке <code>{serial}</code> заменится на номер заказа',
            'type' => 'text',
            'name' => 'description',
            'args' => [
                'value' => 'Оплата заказа #{serial}',
            ],
        ]);

        // api for plugin config
        $this
            ->map([
                'methods' => ['get'],
                'pattern' => '/cart/done/pay',
                'handler' => CartPayAction::class,
            ])
            ->setName('common:sb:pay');
    }
}
