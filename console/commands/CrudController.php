<?php

namespace luya\console\commands;

use Yii;
use yii\helpers\Console;
use yii\helpers\Inflector;
use yii\db\TableSchema;
use luya\Boot;
use luya\helpers\FileHelper;

/**
 * Console command to create a NgRest Crud with Controller, Api and Model based on a SQL Table.
 *
 * @property string $moduleNameWithoutAdminSuffix Get the $moduleName without admin suffix (if any).
 * @author Basil Suter <basil@nadar.io>
 */
class CrudController extends BaseCrudController
{
    /**
     * @var string The name of the module which should be used in order to generate the crud structure e.g `cmsadmin`.
     */
    public $moduleName = null;
    
    /**
     * @var string The name of the model in camelcase notation e.g `NavItem`.
     */
    public $modelName = null;
    
    /**
     * @var string The name of the API endpoint based on the modelName und moduleName selections e.g `api-cms-navitem`.
     */
    public $apiEndpoint = null;
    
    /**
     * @var string The name of the corresponding model database table e.g. `cms_navitem`.
     */
    public $dbTableName = null;
    
    /**
     * @var boolean Whether the i18n text fields will be casted or not.
     */
    public $enableI18n = null;
    
    /**
     * Get the $moduleName without admin suffix (if any).
     *
     * @return mixed Return the module name without admin suffix.
     */
    public function getModuleNameWithoutAdminSuffix()
    {
        return preg_replace('/admin$/', '', $this->moduleName);
    }
    
    /**
     * Get the model name in lower case.
     *
     * @return string Model name in lower case
     */
    public function getModelNameLower()
    {
        return strtolower($this->modelName);
    }
    
    /**
     * Get the camlized model name.
     *
     * @return string Camlized model name
     */
    public function getModelNameCamlized()
    {
        return Inflector::camelize($this->modelName);
    }
    
    /**
     * Get the namepsace to the model.
     *
     * @return string The full namepsace with model name itself.
     */
    public function getModelNamespace()
    {
        return $this->getNamespace() . '\\models\\' . $this->getModelNameCamlized();
    }
    
    /**
     * Generate a suggestion for the api endpoint.
     *
     * @return string Api endpoint suggestion
     */
    public function getApiEndpointSuggestion()
    {
        return 'api-'.$this->getModuleNameWithoutAdminSuffix().'-'.$this->getModelNameLower();
    }
    
    /**
     * Generate a suggestion for the database table name.
     *
     * @return string The database table suggestion.
     */
    public function getDatabaseNameSuggestion()
    {
        return strtolower($this->getModuleNameWithoutAdminSuffix().'_'.Inflector::underscore($this->modelName));
    }
    
    private $_dbTableShema = null;
    
    /**
     * Get the database table schema.
     *
     * @return \yii\db\TableSchema The schmema object
     */
    public function getDbTableShema()
    {
        if ($this->_dbTableShema === null) {
            $this->_dbTableShema = Yii::$app->db->getTableSchema($this->dbTableName, true);
        }
        
        return $this->_dbTableShema;
    }
    
    /**
     * The module object.
     *
     * @return \luya\base\Modue The module object itself, could be the application object as well.
     */
    public function getModule()
    {
        return Yii::$app->getModule($this->moduleName);
    }
    
    /**
     * Get the base path of the module.
     *
     * @return string The module basepath.
     */
    public function getBasePath()
    {
        return $this->getModule()->basePath;
    }
    
    /**
     * Get the namepsace of the module.
     *
     * see {{luya\base\Module::getNamespace}}.
     *
     * @return string The module namespace.
     */
    public function getNamespace()
    {
        return $this->getModule()->getNamespace();
    }
    
    /**
     * Get the controller route for the summary.
     *
     * @return string The summary route like module/controller/action
     */
    public function getSummaryControllerRoute()
    {
        return strtolower($this->moduleName).'/'.Inflector::camel2id($this->getModelNameCamlized()).'/index';
    }
    
    /**
     * Generate the api file content based on its view file.
     *
     * @param string $fileNamespace
     * @param string $className
     * @param string $modelClass
     * @return string
     */
    public function generateApiContent($fileNamespace, $className, $modelClass)
    {
        return $this->view->render('@luya/console/commands/views/crud/create_api.php', [
            'namespace' => $fileNamespace,
            'className' => $className,
            'modelClass' =>  $modelClass,
            'luyaVersion' => Boot::VERSION,
        ]);
    }
    
    /**
     * Generate the controller view file based on its view file.
     * @param string $fileNamespace
     * @param string $className
     * @param string $modelClass
     * @return string
     */
    public function generateControllerContent($fileNamespace, $className, $modelClass)
    {
        return $this->view->render('@luya/console/commands/views/crud/create_controller.php', [
            'namespace' => $fileNamespace,
            'className' => $className,
            'modelClass' =>  $modelClass,
            'luyaVersion' => Boot::VERSION,
        ]);
    }
    
    /**
     * Generate the model content based on its view file.
     *
     * @param string $fileNamepsace
     * @param string $className
     * @param string $apiEndpoint
     * @param TableSchema $schema
     * @param string $i18nFields
     * @return string
     */
    public function generateModelContent($fileNamepsace, $className, $apiEndpoint, TableSchema $schema, $i18nFields)
    {
        $dbTableName = $schema->fullName;
        
        $fields = [];
        $textfields = [];
        $properties = [];
        $ngrestFieldConfig = [];
        foreach ($schema->columns as $k => $v) {
            $properties[$v->name] = $v->type;
            if ($v->isPrimaryKey) {
                continue;
            }
            $fields[] = $v->name;
            
            if ($v->phpType == 'string') {
                $textfields[] = $v->name;
            }
            
            if ($v->type == 'text') {
                $ngrestFieldConfig[$v->name] = 'textarea';
            }
            if ($v->type == 'string') {
                $ngrestFieldConfig[$v->name] = 'text';
            }
            if ($v->type == 'integer' || $v->type == 'bigint' || $v->type == 'smallint') {
                $ngrestFieldConfig[$v->name] = 'number';
            }
            if ($v->type == 'decimal') {
                $ngrestFieldConfig[$v->name] = 'decimal';
            }
            if ($v->type == 'boolean') {
                $ngrestFieldConfig[$v->name] = 'toggleStatus';
            }
        };
        
        return $this->view->render('@luya/console/commands/views/crud/create_model.php', [
            'namespace' => $fileNamepsace,
            'className' => $className,
            'luyaVersion' => Boot::VERSION,
            'apiEndpoint' => $apiEndpoint,
            'dbTableName' => $dbTableName,
            'fields' => $fields,
            'textFields' => $textfields,
            'rules' => $this->generateRules($schema),
            'labels' => $this->generateLabels($schema),
            'properties' => $properties,
            'ngrestFieldConfig' => $ngrestFieldConfig,
            'i18nFields' => $i18nFields,
        ]);
    }

    /**
     * Generate the block build summary based on its view file.
     *
     * @param string $apiEndpoint
     * @param string $apiClassPath
     * @param string $humanizeModelName
     * @param string $controllerRoute
     * @return string
     */
    public function generateBuildSummery($apiEndpoint, $apiClassPath, $humanizeModelName, $controllerRoute)
    {
        return $this->view->render('@luya/console/commands/views/crud/build_summary.php', [
            'apiEndpoint' => $apiEndpoint,
            'apiClassPath' => $apiClassPath,
            'humanizeModelName' => $humanizeModelName,
            'controllerRoute' => $controllerRoute,
        ]);
    }
    
    /**
     * Create Ng-Rest-Model, Controller and Api for an existing Database-Table.
     *
     * @return number
     */
    public function actionCreate()
    {
        if ($this->moduleName === null) {
            Console::clearScreenBeforeCursor();
            $this->moduleName = $this->selectModule(['onlyAdmin' => true, 'hideCore' => true, 'text' => 'Select the Module where the CRUD files should be saved:']);
        }
        
        if ($this->modelName === null) {
            $modelSelection = true;
            while ($modelSelection) {
                $modelName = $this->prompt('Model Name (e.g. Album):', ['required' => true]);
                $camlizeModelName = Inflector::camelize($modelName);
                if ($modelName !== $camlizeModelName) {
                    if ($this->confirm("We have camlized the model name to '$camlizeModelName' do you want to continue with this name?")) {
                        $modelName = $camlizeModelName;
                        $modelSelection = false;
                    }
                } else {
                    $modelSelection = false;
                }
                $this->modelName = $modelName;
            }
        }
        
        if ($this->apiEndpoint === null) {
            $this->apiEndpoint = $this->prompt('Api Endpoint:', ['required' => true, 'default' => $this->getApiEndpointSuggestion()]);
        }

        if ($this->dbTableName === null) {
            $sqlSelection = true;
            while ($sqlSelection) {
                $sqlTable = $this->prompt('Database Table name for the Model:', ['required' => true, 'default' => $this->getDatabaseNameSuggestion()]);
                if ($sqlTable == '?') {
                    foreach ($this->getSqlTablesArray() as $table) {
                        $this->outputInfo("- " . $table);
                    }
                }
                if (isset($this->getSqlTablesArray()[$sqlTable])) {
                    $this->dbTableName = $sqlTable;
                    $sqlSelection = false;
                } else {
                    $this->outputError("The selected database '$sqlTable' does not exists in the list of tables. Type '?' to see all tables.");
                }
            }
        }

        if ($this->enableI18n === null) {
            $this->enableI18n = $this->confirm("Would you like to enable i18n field input for text fields? Only required for multilingual pages.");
        }

        // api content

        $files['api'] = [
            'path' => $this->getBasePath() . DIRECTORY_SEPARATOR . 'apis',
            'fileName' => $this->getModelNameCamlized() . 'Controller.php',
            'content' => $this->generateApiContent($this->getNamespace() . '\\apis', $this->getModelNameCamlized() . 'Controller', $this->getModelNamespace()),
        ];
        
        // controller

        $files['controller'] = [
            'path' =>  $this->getBasePath() . DIRECTORY_SEPARATOR . 'controllers',
            'fileName' => $this->getModelNameCamlized() . 'Controller.php',
            'content' => $this->generateControllerContent($this->getNamespace() . '\\controllers', $this->getModelNameCamlized() . 'Controller', $this->getModelNamespace()),
        ];
        
        // model

        $files['model'] = [
            'path' =>  $this->getBasePath() . DIRECTORY_SEPARATOR . 'models',
            'fileName' => $this->getModelNameCamlized() . '.php',
            'content' => $this->generateModelContent(
                $this->getNamespace() . '\\models',
                $this->getModelNameCamlized(),
                $this->apiEndpoint,
                $this->getDbTableShema(),
                $this->enableI18n
             ),
        ];
        
        foreach ($files as $file) {
            FileHelper::createDirectory($file['path']);
            if (file_exists($file['path'] . DIRECTORY_SEPARATOR . $file['fileName'])) {
                if (!$this->confirm("The File '{$file['fileName']}' already exists, do you want to override the existing file?")) {
                    continue;
                }
            }
            
            if (FileHelper::writeFile($file['path'] . DIRECTORY_SEPARATOR . $file['fileName'], $file['content'])) {
                $this->outputSuccess("Wrote file '{$file['fileName']}'.");
            } else {
                $this->outputError("Error while writing file '{$file['fileName']}'.");
            }
        }
        
        return $this->outputSuccess($this->generateBuildSummery($this->apiEndpoint, $this->getNamespace() . '\\apis\\' . $this->getModelNameCamlized() . 'Controller', $this->getModelNameCamlized(), $this->getSummaryControllerRoute()));
    }
}
