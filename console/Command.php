<?php

namespace luya\console;

use Yii;
use yii\helpers\StringHelper;
use yii\helpers\Inflector;

class Command extends \luya\console\Controller
{
    /**
     * Get selection list of all module types.
     * 
     * @return string
     * @since 1.0.0-beta4
     */
    public function selectModuleType()
    {
        return $this->select('What kind of Module you want to create?', [
            'frontend' => 'Frontend Modules are mainly used to render views.',
            'admin' => 'Admin Modules are mainly used when you Data-Managment should be done inside the Administration area.',
        ]);
    }
    
    /**
     * Get selection list for console commands with defined options.
     * 
     * @param array $options Define behavior of the module selector prompt, options are name-value pairs. The following options are available:
     *
     * - onlyAdmin: boolean, if enabled all not admin modules will not be included
     * - hideCore: boolean, if enabled all core modules (from luya dev team) will be hidden.
     *
     * @return string The name (ID) of the selected module.
     * @since 1.0.0-beta4
     */
    public function selectModule(array $options = [])
    {
        $modules = [];
        foreach (Yii::$app->getModules() as $id => $object) {
            if (!$object instanceof \luya\base\Module) {
                continue;
            }
            if (isset($options['onlyAdmin'])) {
                if (!$object->isAdmin) {
                    continue;
                }
            }
    
            if (isset($options['hideCore'])) {
                if ($object->isCoreModule) {
                    continue;
                }
            }
            $modules[$id] = $id;
        }
    
        $text = (isset($options['text'])) ? $options['text'] : 'Please select a module:';
    
        return $this->select($text, $modules);
    }
    
    /**
     * Generates a class name with camelcase style and specific suffix, if not already provided
     *
     * @param string $string The name of the class, e.g.: hello_word would
     * @param string $suffix The suffix to append on the class name if not eixsts, e.g.: MySuffix
     * @return string The class name e.g. HelloWorldMySuffix
     * @since 1.0.0-beta4
     */
    public function createClassName($string, $suffix = false)
    {
        $name = Inflector::camelize($string);
    
        if ($suffix && StringHelper::endsWith($name, $suffix, false)) {
            $name = substr($name, 0, -(strlen($suffix)));
        }
    
        return $name . $suffix;
    }
    
    /**
     * Get the current Luya Version
     *
     * @return string
     * @since 1.0.0-beta4
     */
    public function getLuyaVersion()
    {
        return \luya\Boot::VERSION;
    }
}
