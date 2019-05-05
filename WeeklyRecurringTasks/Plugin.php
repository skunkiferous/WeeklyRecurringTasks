<?php

namespace Kanboard\Plugin\WeeklyRecurringTasks;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Translator;
use Kanboard\Plugin\WeeklyRecurringTasks\Action\WeeklyRecurringTask;

class Plugin extends Base
{
    public function initialize()
    {
		$this->actionManager->register(new WeeklyRecurringTask($this->container));
    }

    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');
    }

    public function getPluginName()
    {
        return 'Weekly Recurring Tasks';
    }

    public function getPluginDescription()
    {
        return t('Clone closed Tasks with the WEEKLY tag');
    }

    public function getPluginAuthor()
    {
        return 'Sebastien Diot';
    }

    public function getPluginVersion()
    {
        return '1.0.0';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/kanboard/plugin-myplugin';
    }
}

