<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\AbstractLogger;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Vilkas\RateCompass\Client\RateCompassClient;

if (!defined('_PS_VERSION_')) {
    exit;
}

class vk_ratecompass extends Module
{
    protected $config_form = false;

    /** @var AbstractLogger */
    private $logger;

    public function __construct()
    {
        $this->name = 'vk_ratecompass';
        $this->tab = 'advertising_marketing';
        $this->version = '0.0.1';
        $this->author = 'Vilkas Group Oy';
        $this->need_instance = 0;

        /*
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('RateCompass', [], 'Modules.VkRateCompass.Admin');
        $this->description = $this->trans('RateCompass for your Prestashop', [], 'Modules.VkRateCompass.Admin');

        $this->ps_versions_compliancy = ['min' => '1.7.7', 'max' => _PS_VERSION_];

        $this->logger = static::getLogger();
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install(): bool
    {
        Configuration::updateValue('VK_RATECOMPASS_DEBUG_MODE', false);
        Configuration::updateValue('VK_RATECOMPASS_HOST', '');
        Configuration::updateValue('VK_RATECOMPASS_APIKEY', '');

        return parent::install()
            // && $this->installSQL()
            && $this->registerHook('header')
            && $this->registerHook('actionValidateOrder');
    }

    public function uninstall(): bool
    {
        // Configuration::deleteByName('VK_RATECOMPASS_DEBUG_MODE');
        // Configuration::deleteByName('VK_RATECOMPASS_HOST');
        // Configuration::deleteByName('VK_RATECOMPASS_APIKEY');

        return parent::uninstall()
            // && $this->uninstallSQL()
        ;
    }

    public static function getLogger(): Logger
    {
        $logger = new Logger('vk_ratecompass');
        $logger->pushHandler(new StreamHandler(_PS_ROOT_DIR_ . '/var/logs/ratecompass.log'));

        return $logger;
    }

    /**
     * Load the configuration form
     */
    public function getContent(): string
    {
        /*
         * If values have been submitted in the form, process.
         */
        $message = '';
        if ((Tools::isSubmit('submit_vk_ratecompass_module')) == true) {
            if ($this->postProcess()) {
                $message = $this->displayConfirmation(
                    $this->trans('Settings saved successfully.', [], 'Modules.VkRateCompass.Admin')
                );
            } else {
                $message = $this->displayError(
                    $this->trans('Could not save settings.', [], 'Modules.VkRateCompass.Admin')
                );
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $message . $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm(): string
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit_vk_ratecompass_module';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getAllFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];
        return $helper->generateForm($this->getConfigForms());
    }

    protected function getConfigForms(): array
    {
        $form = [
            'general' => $this->getConfigForm(),
        ];

        return $form;
    }

    protected function getAllFormValues(): array
    {
        return array_merge(
            $this->getConfigFormValues(),
        );
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm(): array
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.VkRateCompass.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'name' => 'VK_RATECOMPASS_DEBUG_MODE',
                        'label' => $this->trans('Debug mode', [], 'Modules.VkRateCompass.Admin'),
                        'desc' => $this->trans('Write more debug logs', [], 'Modules.VkRateCompass.Admin'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', [], 'Modules.VkRateCompass.Admin'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', [], 'Modules.VkRateCompass.Admin'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'name' => 'VK_RATECOMPASS_HOST',
                        'label' => $this->trans('RateCompass hostname', [], 'Modules.VkRateCompass.Admin'),
                        'desc' => $this->trans('Get this information from RateCompass. Usually something like: ratecompass.eu', [], 'Modules.VkRateCompass.Admin'),
                        'required' => true
                    ],
                    [
                        'type' => 'text',
                        'name' => 'VK_RATECOMPASS_APIKEY',
                        'label' => $this->trans('RateCompass apikey', [], 'Modules.VkRateCompass.Admin'),
                        'desc' => $this->trans('Get this information from RateCompass dashboard.', [], 'Modules.VkRateCompass.Admin'),
                        'required' => true
                    ],
                ],

                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.VkRateCompass.Admin'),
                ],
            ]
        ];
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues(): array
    {
        return [
            'VK_RATECOMPASS_DEBUG_MODE' => Configuration::get('VK_RATECOMPASS_DEBUG_MODE'),
            'VK_RATECOMPASS_HOST' => Configuration::get('VK_RATECOMPASS_HOST'),
            'VK_RATECOMPASS_APIKEY' => Configuration::get('VK_RATECOMPASS_APIKEY'),
        ];
    }

    /**
     * Save form data.
     */
    protected function postProcess(): bool
    {
        $result = true;

        // basic config form values
        $config_form_values = $this->getConfigFormValues();
        foreach (array_keys($config_form_values) as $key) {
            $result &= Configuration::updateValue($key, Tools::getValue($key));
        }
        try {
            $client = new RateCompassClient(
                Configuration::get("VK_RATECOMPASS_HOST"),
                Configuration::get("VK_RATECOMPASS_APIKEY")
            );
            $compass_id = $client->getCompassID();
            Configuration::updateValue('VK_RATECOMPASS_ID', $compass_id);
        } catch (Exception | ExceptionInterface $e) {
            $msg = $this->trans("Error fetching Compass ID: %error%", ["%error%" => $e->getMessage()], "Modules.VkRateCompass.Admin");
            $this->context->controller->errors[] = $msg;
            return false;
        }
        return $result;
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        // $host = Configuration::get('VK_RATECOMPASS_HOST');
        $host = 'http://localhost:8000';
        $apikey = Configuration::get('VK_RATECOMPASS_APIKEY');
        if (empty($host) || empty($apikey)) {
            return;
        }
        if ('http' !== substr($host, 0, 4)) {
            $host = 'https://' . $host;
        }
        Tools::getValue('id_product');
        $compass_id = Configuration::get('VK_RATECOMPASS_ID');
        $this->context->controller->registerJavascript('ratecompass', $this->_path . '/views/js/embed.js');

        Media::addJsDef([
            'VK_RATECOMPASS_EMBED_URL' => "$host/api/v1/compasses/$compass_id/script.js",
            'VK_RATECOMPASS_PRODUCT_ID' => Tools::getValue('id_product')
        ]);
    }

    /**
     * Operations when an order is created:
     * - Send order data to RateCompass
     */
    public function hookActionValidateOrder(array $params)
    {
        /** @var Customer $cart */
        $customer = $params['customer'];

        /** @var Order $order */
        $order = $params['order'];
        $review_items = [];
        foreach ($order->product_list as $key => $product) {
            $product = new Product($product['id_product']);
            $img = $product->getCover($product->id);
            $product_image_url = $this->context->link->getImageLink(isset($product->link_rewrite) ? $product->link_rewrite : $product->name, (int)$img['id_image']);
            $product_url = Context::getContext()->link->getProductLink($product->id);

            $review_items[] = [
                'product_name' => $product->name[(int)$this->context->language->id],
                'product_id' => $product->id,
                'product_image_url' => $product_image_url,
                'product_url' => $product_url
            ];
        }
        $this->logger->info('some stuff', [
            'customer_first_name' => $customer->firstname,
            'customer_last_name' => $customer->lastname,
            'customer_email' => $customer->email,
            'language' => $this->context->language->iso_code,
            'order_id' => $order->id,
            'order_number' => $order->reference,
            'order_created_at' => (new DateTime($order->date_add))->format(DATE_ATOM),
            'review_items' => $review_items
        ]);

        $client = new RateCompassClient(
            Configuration::get("VK_RATECOMPASS_HOST"),
            Configuration::get("VK_RATECOMPASS_APIKEY")
        );
        $client->postOrder(Configuration::get("VK_RATECOMPASS_ID"), [
            'customer_first_name' => $customer->firstname,
            'customer_last_name' => $customer->lastname,
            'customer_email' => $customer->email,
            'language' => $this->context->language->iso_code,
            'order_id' => $order->id,
            'order_number' => $order->reference,
            'order_created_at' => (new DateTime($order->date_add))->format(DATE_ATOM),
            'review_items' => $review_items
        ]);
    }
}
