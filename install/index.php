<?php

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use ProklUng\Module\Boilerplate\Module;
use ProklUng\Module\Boilerplate\ModuleUtilsTrait;

Loc::loadMessages(__FILE__);

class proklung_redis extends CModule
{
    use ModuleUtilsTrait;

    /**
     * proklung_redis constructor.
     */
    public function __construct()
    {
        $arModuleVersion = [];

        include __DIR__.'/version.php';

        if (is_array($arModuleVersion)
            &&
            array_key_exists('VERSION', $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }

        $this->MODULE_FULL_NAME = 'redis';
        $this->MODULE_VENDOR = 'proklung';
        $prefixLangCode = 'REDIS';

        $this->MODULE_NAME = Loc::getMessage($prefixLangCode.'_MODULE_NAME');
        $this->MODULE_ID = $this->MODULE_VENDOR.'.'.$this->MODULE_FULL_NAME;

        $this->MODULE_DESCRIPTION = Loc::getMessage($prefixLangCode.'_MODULE_DESCRIPTION');
        $this->MODULE_GROUP_RIGHTS = 'N';
        $this->PARTNER_NAME = Loc::getMessage($prefixLangCode.'_MODULE_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage($prefixLangCode.'MODULE_PARTNER_URI');

        $this->moduleManager = new Module(
            [
                'MODULE_ID' => $this->MODULE_ID,
                'VENDOR_ID' => $this->MODULE_VENDOR,
                'MODULE_VERSION' => $this->MODULE_VERSION,
                'MODULE_VERSION_DATE' => $this->MODULE_VERSION_DATE,
                'ADMIN_FORM_ID' => $this->MODULE_VENDOR.'_settings_form',
            ]
        );

        $this->moduleManager->addModuleInstance($this);
        $this->options();
    }

    /**
     * @inheritDoc
     */
    public function UnInstallFiles()
    {
        parent::UnInstallFiles();

        $rootDir = Application::getDocumentRoot().'/';
        $binFile = '/enqueue';

        @unlink($rootDir . '../../bin' . $binFile);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function InstallFiles()
    {
        parent::InstallFiles();

        $rootDir = Application::getDocumentRoot().'/';
        $binFile = '/enqueue';

        if (is_dir($rootDir . '../../bin')) {
            $src = __DIR__. '/bin' . $binFile;
            copy($src, $rootDir . '../../bin' . $binFile);
        }

        return true;
    }
}
