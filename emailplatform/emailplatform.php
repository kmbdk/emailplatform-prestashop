<?php
/**
 * 2007-2019 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 *
 **/

if (!defined('_PS_VERSION_'))
    exit;

class emailplatform extends Module {

    //{{{ properties
    const DIR_VIEWS = 'views/';
    const CUSTOM_FIELD_PREFIX = 'ps_';
    const API_URL = 'https://api.mailmailmail.net/v1.1/';

    public $dir_tpl;
    public $ps_version;
    public $id_shop;
    public $id_shop_group;
    public $id_lang;

    /**
     * emailplatform api credentials
     * @var array
     */
    public $api_username;
    public $api_token;
    public $cronUrl;
    public $cronSecureKey;

    /**
     * complete prestashop URL including subdirectory
     * @var string
     */
    public $shopDomain;

    /**
     * internal storage of the  list id
     * @var string
     */
    private $id_list;

    /**
     * the custom fields that will be exported
     * @var array
     * @var object
     */
    private $customfields;
    private $customfieldids;

    /**
     * prestashop module configuration
     * @var object
     */
    private $_moduleSettings;

    /**
     * prestashop module shop specific configuration
     * @var object
     */
    private $_shopSettings;
    protected $_errors = array();
    protected $_conf;

    /**
     * define default custom fields
     * @var array
     */
    public $customfieldsDefault = array(
        'ps_id_shop' => array(
            'fieldname' => 'Shop_ID',
            'empFieldtype' => 'number',
            'objProperty' => 'id_shop',
            'datatype' => 'int'
        ),
        'ps_id_customer' => array(
            'fieldname' => 'Customer_ID',
            'empFieldtype' => 'number',
            'objProperty' => 'id',
            'datatype' => 'int'
        ),
        'ps_birthday' => array(
            'fieldname' => 'Birthday',
            'empFieldtype' => 'date',
            'objProperty' => 'birthday',
            'datatype' => 'string'
        ),
        'ps_language' => array(
            'fieldname' => 'Language',
            'empFieldtype' => 'text',
            'datatype' => 'string',
            'method' => array(
                'type' => 'static',
                'name' => 'getLangIsoCode',
                'params' => 'id',
                'returnKey' => 'iso_code'
            ),
        )
    );

    public function __construct() {
        
        $this->name = 'emailplatform';
        $this->version = '1.0.1';
        $this->tab = 'emailing';
        $this->author = 'emailplatform.com';

        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('eMailPlatform Subscription Module');
        $this->description = $this->l('Import and export new subscribers to eMailPlatform');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        // ps 1.5
        $this->bootstrap = true;
        $this->ps_version = $this->_getPsMainVersion();

        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);

        $this->id_lang = $this->context->cookie->id_lang;
        $this->id_shop = $this->context->shop->id;
        $this->id_shop_group = $this->context->shop->id_shop_group;
        $psDir = $this->context->shop->physical_uri;

        $this->shopDomain = Configuration::get('PS_SHOP_DOMAIN') . $psDir;
        $moduleURL = 'http://' . $this->shopDomain . 'modules/' . $this->name;

        // get correct template dir
        $this->dir_tpl = self::DIR_VIEWS . 'templates/admin/' . $this->ps_version . '/getContent.tpl';

        $this->_moduleSettings = json_decode(Configuration::get(
                        'EMAILPLATFORM_SETTINGS',
                        null,
                        $this->id_shop_group,
                        $this->id_shop
        ));

        if ($this->_moduleSettings) {

            $this->api_token = $this->_moduleSettings->api_token;
            $this->api_username = $this->_moduleSettings->api_username;
            $this->id_list = $this->_moduleSettings->id_list;
            $this->customfields = $this->_moduleSettings->customfields;
            $this->customfieldids = $this->_moduleSettings->customfieldids;
            
        }

        $this->cronSecureKey = md5($this->api_token . ':' . $this->api_username);
        $this->cronUrl = $moduleURL . '/cron.php?secureKey=' . $this->cronSecureKey;
    }

    public function install() {
        // Check PS version compliancy
        /* if (version_compare(_PS_VERSION_, '1.5', '>=')
          || version_compare(_PS_VERSION_, '1.4', '<='))
          {
          $notCompliant = 'The version of your module is not compliant with your PrestaShop version.';
          $this->_errors[] = $this->l($notCompliant);
          return false;
          } */

        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        if (!parent::install() || !$this->registerHook('newOrder') || !$this->registerHook('createAccount') || !$this->registerHook('backOfficeHeader') || !$this->registerHook('header') || !$this->registerHook('actionCustomerAccountUpdate')
        )
            return false;
        return true;
    }

    public function uninstall() {

        if (!parent::uninstall() || !Configuration::deleteByName('EMAILPLATFORM_SETTINGS')
        )
            return false;
        return true;
    }

    public function getContent() {
        
        $this->_errors = array('apiInfo' => '', 'listOptions' => '');

        if ($this->ps_version == '1.5')
            $this->_clearCache($this->dir_tpl);

        if (Tools::isSubmit('saveSettings')) {
            $this->api_username = Tools::getvalue('emp_api_username');
            $this->api_token = Tools::getvalue('emp_api_token');

            if (empty($this->api_username))
                $this->_errors['apiInfo'] = $this->l('eMailPlatform API username missing');

            if (empty($this->api_token))
                $this->_errors['apiInfo'] = $this->l('eMailPlatform API token missing');

            if (empty($this->_errors['apiInfo'])) {
                $moduleSettings = json_encode(array(
                    'api_token' => $this->api_token,
                    'api_username' => $this->api_username,
                    'id_list' => '',
                    'customfields' => '',
                    'customfieldids' => ''
                ));

                Configuration::updatevalue(
                        'EMAILPLATFORM_SETTINGS',
                        $moduleSettings,
                        false,
                        $this->id_shop_group,
                        $this->id_shop
                );

                $this->_conf = $this->l('API Info saved');
            }
        }

        if (Tools::isSubmit('saveOptions')) {
            
            $this->id_list = Tools::getvalue('list');

            if (empty($this->id_list))
                $this->_errors['listOptions'] = $this->l('No list chosen');

            if (empty($this->_errors['listOptions'])) {
                $customfields = array();

                if (Tools::getvalue('custom_fields'))
                    foreach (Tools::getvalue('custom_fields') as $field)
                        $customfields[] = array('fieldname' => $field);

                // always save shop id so we can send it to  later
                // it is needed to identify and unsubscribe customers that subscribed via
                // the prestashop newsletter module
                $customfields[] = array('fieldname' => 'ps_id_shop');
                $this->customfields = json_decode(json_encode($customfields));

                $this->_moduleSettings->id_list = $this->id_list;
                $this->_moduleSettings->customfields = $this->customfields;
                
                $moduleSettings = json_encode($this->_moduleSettings);

                Configuration::updatevalue(
                        'EMAILPLATFORM_SETTINGS',
                        $moduleSettings,
                        false,
                        $this->id_shop_group,
                        $this->id_shop
                );
                
                // create or get the fields form emp
                if(!empty($this->id_list))
                    $this->createCustomFields();

                // @todo - try to prevent double assigning this property
                $this->listsUrl = 'lists/' . $this->id_list;

                $this->_conf = $this->l('List options saved');
            }
        }

        if (Tools::getvalue('syncData'))
            $this->synchronise();

        
        if (Tools::isSubmit('exportToEMP'))
            $this->exportCustomer('ALL');
            

        // test api connection
        $api_test = $this->_requestData('POST', 'Test/TestUserToken');
        $Lists = $this->getemailplatformLists();

        if ($api_test !== true) {

            $Lists = array();

            if (!empty($this->api_token) && !empty($this->api_username))
                $this->_errors['apiInfo'] = $this->l('API information not correct.');
        } else {
            
            if (!empty($this->id_list))
            $this->createCustomFields();
            
        }

        $viewCustomFieldsDefault = $this->customfieldsDefault;
        // remove shop id from optional selectable custom fields
        // shop id always needs to go to 
        unset($viewCustomFieldsDefault['ps_id_shop']);

        $this->context->smarty->assign(array(
            'api_token' => $this->api_token,
            'api_username' => $this->api_username,
            'selected_list' => $this->id_list,
            'lists' => $Lists,
            'customfieldsDefault' => $viewCustomFieldsDefault,
            'customfields' => $this->customfields,
            'cronUrl' => $this->cronUrl,
            'errors' => $this->_errors,
            'conf' => $this->_conf
        ));
        
        return $this->context->smarty->fetch(dirname(__FILE__) . '/' . $this->dir_tpl);
        
    }

    public function exportCustomer($customers = 'LAST') {
        // add or update specific customer
        if (is_numeric($customers)) {
            $customer = new Customer($customers);
            $subscriber = $this->prepareSubscriber($customer->id);

            if ($subscriber !== false)
                $this->addSubscribers($subscriber);
        } else if ($customers == 'LAST') {
            $lastCustomer = self::getLastCustomer();
            $customer = new Customer($lastCustomer['id_customer']);
            $subscriber = $this->prepareSubscriber($customer->id);
            
            if ($subscriber !== false)
                $this->addSubscribers($subscriber);
        } else if ($customers == 'ALL') {
            foreach (Customer::getCustomers() as $customer) {
                
                $subscriber = $this->prepareSubscriber($customer['id_customer']);
                if ($subscriber !== false)
                    $this->addSubscribers($subscriber);
                    
            }
            
            $customfields = array();
            
            // check for those who subscribed via the prestashop newsletter module
            foreach (self::getBlockNewsletterSubscribers() as $subscriber) {
                
                $customfields[] = array(
                    'fieldid' => $this->customfieldids->{$this->customfieldsDefault['ps_id_shop']['fieldname']},
                    'value' => (int) $subscriber['id_shop']
                );
                $params = array(
                    'listid' => $this->id_list,
                    'emailaddress' => $subscriber['email'],
                    'mobile' => false,
                    'mobile_prefix' => false,
                    'contactFields' => $customfields,
                    'add_to_autoresponders' => true,
                    'skip_listcheck' => false,
                    'confirmed' => true
                );
                $this->addSubscribers($params);
                
            }
        }
    }

    public function updateCustomer($id_customer) {

        $customer = new Customer($id_customer);

        $subscriber = $this->prepareSubscriber($customer->id, true);

        if ($subscriber !== false)
            $this->updateSubscribers($subscriber);
        
    }

    public function createCustomField($fieldname, $fieldtype, $fieldsettings = array()) {

        $params = array(
            'name' => $fieldname,
            'fieldtype' => $fieldtype,
            'fieldsettings' => $fieldsettings,
            'listids' => $this->id_list
        );
        
        if ($fieldid = $this->_requestData('POST', '/CustomFields/CreateCustomField', $params))
            return $fieldid;
        return false;
        
    }

    public function createCustomFields() {

        if (!$this->customfields)
            return false;
        
        $customfieldids = array();

        foreach ($this->customfields as $value) {
            
            $settings = array();
            
            $cfData = $this->customfieldsDefault[$value->fieldname];
            $fieldname = self::CUSTOM_FIELD_PREFIX . $cfData['fieldname'];

            if ($this->customFieldExists($fieldname))
                continue;
            
            if($cfData['empFieldtype'] == 'date')
                $settings = array(
                    'format_1' => 'day',
                    'format_2' => 'month',
                    'format_3' => 'year',
                    'start_year' => date('Y', strtotime("-100 year", time())),
                    'end_year' => date('Y', strtotime("+100 year", time()))
                );
            

            $fieldid = $this->createCustomField($fieldname, $cfData['empFieldtype'], $settings);
            
            $customfieldids[$cfData['fieldname']] = $fieldid;
            
        }

        if (!empty($customfieldids)) {
            
            if(is_object($this->customfieldids)){
                foreach($customfieldids as $key => $val){
                    $this->customfieldids->{$key} = $val;
                }
            } else {
                $this->customfieldids = $customfieldids;
            }
            
            $this->_moduleSettings->customfieldids = $this->customfieldids;

            $moduleSettings = json_encode($this->_moduleSettings);

            Configuration::updatevalue(
                    'EMAILPLATFORM_SETTINGS',
                    $moduleSettings,
                    false,
                    $this->id_shop_group,
                    $this->id_shop
            );
            
        }
    }

    public function customFieldExists($fieldname) {
        
        $customfieldids = array();
        
        foreach ($this->getCustomFields($this->id_list) as $customfield){

            if (isset($customfield->fieldname) && $customfield->fieldname == $fieldname){
                if(is_object($this->customfieldids)){ 
                    $this->customfieldids->{$fieldname} = $customfield->fieldid;
                } else {
                    $customfieldids[$fieldname] = $customfield->fieldid;
                    $this->customfieldids = $customfieldids;
                }
                    
                return true;
            }
        }
        return false;
        
    }

    public function prepareCustomFields($id_customer) {
        $customer = new Customer($id_customer);
        $customfields = array();
        $i = 0;

        foreach ($this->customfields as $value) {
            $cfData = $this->customfieldsDefault[$value->fieldname];
            $datatype = $cfData['datatype'];
            $customfields[$i]['fieldid'] = $this->customfieldids->{$cfData['fieldname']};
            //$customfields[$i]['Key'] = self::CUSTOM_FIELD_PREFIX . $cfData['fieldname'];

            if (isset($cfData['objProperty'])){
                
                if($cfData['empFieldtype'] == 'date'){
                    $customfields[$i]['value'] = date('d-m-Y', strtotime($customer->{$cfData['objProperty']}));
                } else {
                    $customfields[$i]['value'] = $customer->{$cfData['objProperty']};
                }
                
            } else if (isset($cfData['method'])) {
                
                $params = $customer->{$cfData['method']['params']};
                $methodName = $cfData['method']['name'];
                $method = $this->{$methodName}($params);

                if ($cfData['method']['type'] == 'static')
                    $method = call_user_func(array('self', $methodName), $params);

                $customfields[$i]['value'] = $method[$cfData['method']['returnKey']];

                if ($cfData['empFieldtype'] == 'date'){
                    $customfields[$i]['value'] = date('d-m-Y', strtotime(Tools::substr($customfields[$i]['value'], 0, 10)));
                        
                }
                    
            }
            settype($customfields[$i]['value'], $datatype);
            $i++;
        }
        
        return $customfields;
    }

    public function addSubscribers($params) {

        if(is_int($this->_requestData('POST', 'Subscribers/AddSubscriberToList', $params)))
            return true;
        return false;
        
    }

    public function updateSubscribers($params) {
        
        $req = $this->_requestData('POST', 'Subscribers/UpdateSubscriber', $params);
        if($req[0])
            return true;
        return false;
        
    }

    public function prepareSubscriber($id_customer, $update = false) {
        $customer = new Customer($id_customer);

        if ($customer->newsletter) {
            
            $name = array(
                array(
                    'fieldid' => 2,
                    'value' => $customer->firstname
                ),
                array(
                    'fieldid' => 3,
                    'value' => $customer->lastname
                )
            );
            
            $contactFields = array_merge($name, $this->prepareCustomFields($customer->id));
            
            if($update){
                return array(
                    'listid' => $this->id_list,
                    'subscriberid' => false,
                    'emailaddress' => $customer->email,
                    'mobile' => false,
                    'mobilePrefix' => false,
                    'customfields' => $contactFields
                );
            } else {
                return array(
                    'listid' => $this->id_list,
                    'emailaddress' => $customer->email,
                    'mobile' => false,
                    'mobile_prefix' => false,
                    'contactFields' => $contactFields,
                    'add_to_autoresponders' => true,
                    'skip_listcheck' => false,
                    'confirmed' => true
                );
            }
        }
        return false;
    }

    private function _requestData($method = false, $endpoint = false, $params = array()) {

        $header = array(
            "Accept: application/json; charset=utf-8",
            "ApiUsername: " . $this->api_username,
            "ApiToken: " . $this->api_token
        );

        if ($method == false)
            return false;

        if ($method == 'POST') {

            try {
                // open connection
                $ch = curl_init();

                // add the setting to the fields
                // $data = array_merge($fields, $this->settings);
                $encodedData = http_build_query($params, '', '&');

                $url = self::API_URL . $endpoint;

                // set the url, number of POST vars, POST data
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_POST, count($params));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);
                // disable for security
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

                // execute post
                $result = curl_exec($ch);

                // close connection
                curl_close($ch);
                return json_decode($result);
            } catch (Exception $error) {
                return $error->GetMessage();
            }
        } elseif ($method == 'GET') {

            // open connection
            $ch = curl_init();
            if (!empty($params)) {
                $url = self::API_URL . $endpoint .= "?" . http_build_query($params, '', '&');
            } else {
                $url = self::API_URL . $endpoint;
            }

            // set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            // disable for security
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            // execute post
            $result = curl_exec($ch);

            // close connection
            curl_close($ch);
            // return $result;
            return json_decode($result);
        } else {
            return false;
        }
    }

    public function getemailplatformLists() {
        return $this->_requestData('GET', 'Users/GetLists');
    }
    
    public function getCustomFields($listid) {
        $params = array(
            'listids' => $listid
        );
        return $this->_requestData('GET', 'Lists/GetCustomFields', $params);
    }

    public function getCustomFieldvalue($key, $customfields) {
        $customfield = null;
        foreach ($customfields as $field) {
            $field = (object) $field;
            if ($field->Key == $key) {
                $customfield = $field->value;
                break;
            }
        }
        return $customfield;
    }
    
    public function getTodaysUnsubscribesEMP(){
        
        /** Get all unsubscribes from eMailPlatform today **/
        
        $limit = 1000;
        $offset = 0;
        $stack = array();
        
        do {
            
            $params = array(
                'searchinfo' => array(
                    'List' => $this->id_list,
                    'Status' => 'unsubscribed',
                    'DateSearch' => array(
                        'type' => 'after',
                        'StartDate' => date('d-m-Y')
                    )
                ),
                'countonly' => false,
                'limit' => $limit,
                'offset' => $offset
            );
            
            $subscribers = $this->_requestData('GET', 'Subscribers/GetSubscribers', $params);
            $count = count($subscribers);
            
            $stack = array_merge($stack, $subscribers);
            
            $offset = $offset + $limit;

        }while($count == $limit);
        
        return $stack;
        
    }

    // sync with eMailPlatform
    public function synchronise() {
        
        $unsubscribes = $this->getTodaysUnsubscribesEMP();
        
        foreach($unsubscribes as $unsubscriber){
            
            // 0 = not subscribed
            // 1 = guest subscription
            // 2 = customer subscription
            $register_status = $this->isPsNewsletterRegistered($unsubscriber->emailaddress);
            
            // unsubscribe from prestashop
            if($register_status == 1){
                $this->unregisterFromPsNewsletter($unsubscriber->emailaddress, 1);
            }elseif($register_status == 2){
                $this->unregisterFromPsNewsletter($unsubscriber->emailaddress, 2);
            }
            
        }
        
    }

    public static function getLastCustomer() {
        $customers = Customer::getCustomers();
        return end($customers);
    }

    public static function getLangIsoCode($id_customer) {
        $customer = new Customer($id_customer);
        return array('iso_code' => Language::getIsoById($customer->id_lang));
    }

    
    public static function getBlockNewsletterSubscribers() {
        $dbquery = new DbQuery();
        $dbquery->select('*');
        $dbquery->from('emailsubscription', 'n');
        $dbquery->where('n.`active` = 1');
        return Db::getInstance()->executeS($dbquery->build());
    }
    
    public function getPsUnsubscribes(){
        
        $sql = 'SELECT `email` 
                FROM '._DB_PREFIX_.'customer
                WHERE newsletter = 0
                AND newsletter_date_add > 1970-01-01
                AND id_shop = '.$this->id_shop;
        
        return Db::getInstance()->executeS($sql);
        
    }
    
    public function isPsNewsletterRegistered($email){
        
        $sql = 'SELECT `email`
                FROM '._DB_PREFIX_.'emailsubscription
                WHERE `email` = \''.pSQL($email).'\'
                AND active = 1
                AND id_shop = '.$this->id_shop;
        if (Db::getInstance()->getRow($sql)) {
            return 1; // guest subscription
        }
        
        $sql = 'SELECT `newsletter`
                FROM '._DB_PREFIX_.'customer
                WHERE `email` = \''.pSQL($email).'\'
                AND id_shop = '.$this->id_shop.'
                AND newsletter = 1';
        if (Db::getInstance()->getRow($sql)) {
            return 2; // customer subscribed
        }
        return 0; // not subscribed email
        
    }
    
    public function isSubscribedEMP($email){
        
        $params = array(
            'listids' => $this->id_list,
            'emailaddress' => $email,
            'mobile' => false,
            'mobilePrefix' => false,
            'subscriberid' => false,
            'activeonly' => true,
            'not_bounced' => false,
            'return_listid' => false
        );
        $result = $this->_requestData('GET', 'Subscribers/IsSubscriberOnList', $params);
        
        if(empty($result))
            return false;
        
        return true;
        
    }
    
    public function unsubscribeEMP($email){
        $params = array(
            'listid' => $this->id_list, 
            'emailaddress' => $email,
            'subscriberid' => false, 
            'skipcheck' => false,
            'statid' => false
        );
        
        return $this->_requestData('POST', 'Subscribers/UnsubscribeSubscriberEmail', $params);
        
    }

    public  function unregisterFromPsNewsletter($email, $register_status) {
        
        if ($register_status == 1)
            // guest subscription
            $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'emailsubscription WHERE `email` = \'' . pSQL($email) . '\' AND id_shop = ' . (int) $this->id_shop;
            
        else if ($register_status == 2)
            // customer subscription
            $sql = 'UPDATE ' . _DB_PREFIX_ . 'customer SET `newsletter` = 0 WHERE `email` = \'' . pSQL($email) . '\' AND id_shop = ' . (int) $this->id_shop;

        if (!isset($sql) || !Db::getInstance()->execute($sql))
            return false;
        return true;
        
    }
    
    // handle eMailPlatform when saving account
    public function hookActionCustomerAccountUpdate($params){
        $customer = $params['customer'];
        if($customer->newsletter){
            
            // subscribe if not subscribed
            if(!$this->isSubscribedEMP($customer->email)){
                $this->exportCustomer($customer->id);
            } else {
                // update information if already subscribed
                $subscriber = $this->prepareSubscriber($customer->id, true);
                $this->updateSubscribers($subscriber);
            }
            
        } else {
            
            // unsubscribe if subscribed
            if($this->isSubscribedEMP($customer->email)){
                $this->unsubscribeEMP($customer->email);
            }
            
        }
    }
    
    public function hookNewOrder($params) {
        $this->exportCustomer('LAST');
    }

    public function hookCreateAccount($params) {
        $this->hookNewOrder($params);
    }

    public function hookBackOfficeHeader($params) {
        $cssFile = self::DIR_VIEWS . '/css/' . $this->name . '.css';
        return '<link href="' . $cssFile . '" rel="stylesheet" type="text/css" />';
    }

    /**
     * send subscribers which subscribed via prestashop newsletter module to 
     */
    public function hookHeader($params) {
        
        if (Tools::isSubmit('submitNewsletter')) {
            $customfields = array();
            $customfields[] = array(
                'fieldid' => $this->customfieldids->{$this->customfieldsDefault['ps_id_shop']['fieldname']},
                'value' => (int) $this->id_shop
            );
            $params = array(
                'listid' => $this->id_list,
                'emailaddress' => Tools::getvalue('email'),
                'mobile' => false,
                'mobile_prefix' => false,
                'contactFields' => $customfields,
                'add_to_autoresponders' => true,
                'skip_listcheck' => false,
                'confirmed' => false
            );
            
            $this->addSubscribers($params);
            
        }
        
    }

    public function _getPsMainVersion() {
        return Tools::substr(_PS_VERSION_, 0, 3);
    }

}