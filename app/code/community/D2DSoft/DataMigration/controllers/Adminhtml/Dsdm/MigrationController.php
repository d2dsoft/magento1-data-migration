<?php

/**
 * D2dSoft
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL v3.0) that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL: https://d2d-soft.com/license/AFL.txt
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade this extension/plugin/module to newer version in the future.
 *
 * @author     D2dSoft Developers <developer@d2d-soft.com>
 * @copyright  Copyright (c) 2021 D2dSoft (https://d2d-soft.com)
 * @license    https://d2d-soft.com/license/AFL.txt
 */

class D2DSoft_DataMigration_Adminhtml_Dsdm_MigrationController
    extends Mage_Adminhtml_Controller_Action
{
    /**
     * @var D2dHttpApp
     */
    protected $app;

    /**
     * @var D2DSoft_DataMigration_Helper_Data
     */
    protected $helper;

    protected function _isAllowed(){
        return Mage::getSingleton('admin/session')->isAllowed('d2dsoft/datamigration');
    }

    public function indexAction()
    {
        if(!$this->getHelper()->isInstallLibrary()){
            return $this->_redirect('*/*/license');
        }
        $this->loadLayout();
        $this->_setActiveMenu('d2dsoft/datamigration')
            ->_title($this->getHelper()->__('Data Migration'));
        $app = $this->_getMigrationApp();
        $target = $app->getInitTarget();
        $response = $app->process(D2dInit::PROCESS_INIT);
        $html = '';
        if($response['status'] == D2dCoreLibConfig::STATUS_SUCCESS){
            $html = $response['html'];
        }
        $this->getLayout()
            ->getBlock('dsdm.index')
            ->setHtmlContent($html)
            ->setMigrationMessage($this->getMessage())
            ->setJsConfig($target->getConfigJs());
        $this->renderLayout();
    }

    public function settingAction(){
        if(!$this->getHelper()->isInstallLibrary()){
            return $this->_redirect('*/*/license');
        }
        $app = $this->_getMigrationApp();
        $target = $app->getInitTarget();

        if($this->getRequest()->isPost()){
            $keys = array(
                'license', 'storage', 'taxes', 'manufacturers', 'customers', 'orders', 'reviews', 'delay', 'retry', 'src_prefix', 'target_prefix', 'other'
            );
            foreach($keys as $key){
                $target->dbSaveSetting($key, $this->getRequest()->getParam($key));
            }
            $this->setMessage('success', 'Save successfully.');
        }

        $settings = $target->dbSelectSettings();
        $this->loadLayout();
        $this->_setActiveMenu('d2dsoft/datamigration')
            ->_title($this->getHelper()->__('Setting'));
        $this->getLayout()
            ->getBlock('dsdm.setting')
            ->setMigrationSetting($settings)
            ->setMigrationMessage($this->getMessage());
        $this->renderLayout();
    }

    public function ajaxAction(){
        $type = $this->getRequest()->getParam('action_type', 'import');
        return $this->$type();
    }

    public function licenseAction(){
        if(!ini_get('allow_url_fopen')){
            $this->setMessage('error', 'The PHP "allow_url_fopen" must is enabled. Please follow <a href="https://www.a2hosting.com/kb/developer-corner/php/using-php.ini-directives/php-allow-url-fopen-directive" target="_blank">here</a> to enable the setting.');
        }
        if (!extension_loaded('zip')) {
            $this->setMessage('error', 'PHP Zip extension is not installed. Please install the Zip extension.');
        }
        /*if (!function_exists('eval')) {
            $this->setMessage('error', 'Please enable the eval function.');
        }*/
        $this->loadLayout();
        $this->_setActiveMenu('d2dsoft/datamigration')
            ->_title($this->getHelper()->__('Data Migration'));
        $this->getLayout()
            ->getBlock('dsdm.license')
            ->setMigrationMessage($this->getMessage());
        $this->renderLayout();
    }

    public function setupAction(){
        $license = $this->getRequest()->getParam('license');
        $install = $this->_downloadAndExtraLibrary($license);
        if(!$install){
            return $this->_redirect('*/*/license');
        }
        $app = $this->_getMigrationApp();
        $initTarget = $app->getInitTarget();
        $install_db = $initTarget->setupDatabase($license);
        if(!$install_db){
            return $this->_redirect('*/*/license');
        }
        return $this->_redirect('*/*/index');
    }

    public function import(){
        $app = $this->_getMigrationApp();
        $process = $this->getRequest()->getParam('process');
        if(!$process || !in_array($process, array(
                D2dInit::PROCESS_SETUP,
                D2dInit::PROCESS_CHANGE,
                D2dInit::PROCESS_UPLOAD,
                D2dInit::PROCESS_STORED,
                D2dInit::PROCESS_STORAGE,
                D2dInit::PROCESS_CONFIG,
                D2dInit::PROCESS_CONFIRM,
                D2dInit::PROCESS_PREPARE,
                D2dInit::PROCESS_CLEAR,
                D2dInit::PROCESS_IMPORT,
                D2dInit::PROCESS_RESUME,
                D2dInit::PROCESS_REFRESH,
                D2dInit::PROCESS_AUTH,
                D2dInit::PROCESS_FINISH))){
            $this->responseJson(array(
                'status' => 'error',
                'message' => 'Process Invalid.'
            ));
            return;
        }
        $response = $app->process($process);
        $this->responseJson($response);
        return;
    }

    public function download(){
        $app = $this->_getMigrationApp();
        $app->process(D2dInit::PROCESS_DOWNLOAD);
    }

    protected function _downloadAndExtraLibrary($license = ''){
        $url = D2DSoft_DataMigration_Helper_Data::PACKAGE_URL;
        $library_folder = $this->getHelper()->getLibraryFolder();
        if(!is_dir($library_folder))
            @mkdir($library_folder, 0777, true);
        $tmp_path = $library_folder . '/resources.zip';
        $data = array(
            'license' => $license
        );
        $client = new Zend_Http_Client();
        $client->setUri($url)
            ->setStream($tmp_path)
            ->setMethod(Zend_Http_Client::POST)
            ->setRawData(json_encode($data));
        try {
            $client->request()->getBody();
            $zip     = new Zend_Filter_Compress_Zip();
            $zip->setTarget($library_folder);
            $zip->decompress($tmp_path);
            @unlink($tmp_path);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function _getMigrationApp()
    {
        if($this->app){
            return $this->app;
        }
        /* @var $session Mage_Admin_Model_Session */
        $session = Mage::getSingleton('admin/session');
        $user = $session->getUser();
        $library_folder = $this->getHelper()->getLibraryFolder();
        include_once $this->getHelper()->getInitLibrary();
        D2dInit::initEnv();
        $app = D2dInit::getAppInstance(D2dInit::APP_HTTP, D2dInit::TARGET_RAW, 'magento');
        $app->setRequest($this->getRequest()->getParams());
        $config = array();
        $config['user_id'] = $user->getId();
        $config['upload_dir'] = $library_folder . '/files';
        $config['upload_location'] = ltrim($this->getHelper()->getLibraryLocation()) . '/files';
        $config['log_dir'] = $library_folder . '/log';
        $app->setConfig($config);
        $this->app = $app;
        return $this->app;
    }

    protected function responseJson($data)
    {
        $this->getResponse()
            ->clearHeaders()
            ->setHeader('Content-type', 'application/json' ,true);
        $this->getResponse()
            ->setBody(Mage::helper('core')->jsonEncode($data));
        return;
    }

    public function setMessage($type, $message){
        /* @var $session Mage_Admin_Model_Session */
        $session = Mage::getSingleton('admin/session');
        $messages = $session->getMigrationMessage();
        if(!$messages)
            $messages = array();
        $messages[] = array(
            'type' => $type,
            'message' => $message
        );
        $session->setMigrationMessage($messages);
        return $this;
    }

    public function getMessage(){
        /* @var $session Mage_Admin_Model_Session */
        $session = Mage::getSingleton('admin/session');
        $messages = $session->getMigrationMessage();
        $session->setMigrationMessage(array());
        return $messages;
    }

    public function getHelper(){
        if(!$this->helper){
            $this->helper = Mage::helper('dsdm');
        }
        return $this->helper;
    }
}