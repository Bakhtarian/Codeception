<?php
namespace Codeception\Test;

use Codeception\Configuration;
use Codeception\Exception\ModuleException;
use Codeception\Lib\Di;
use Codeception\Lib\Notification;
use Codeception\Scenario;
use Codeception\TestInterface;

/**
 * Represents tests from PHPUnit compatible format.
 */
class Unit extends \Codeception\PHPUnit\TestCase implements
    Interfaces\Reported,
    Interfaces\Dependent,
    TestInterface
{
    use \Codeception\Test\Feature\Stub;

    /**
     * @var Metadata
     */
    private $metadata;

    public function getMetadata()
    {
        if (!$this->metadata) {
            $this->metadata = new Metadata();
        }
        return $this->metadata;
    }

    protected function _setUp()
    {
        if ($this->getMetadata()->isBlocked()) {
            if ($this->getMetadata()->getSkip() !== null) {
                $this->markTestSkipped($this->getMetadata()->getSkip());
            }
            if ($this->getMetadata()->getIncomplete() !== null) {
                $this->markTestIncomplete($this->getMetadata()->getIncomplete());
            }
            return;
        }

        try {
        /** @var $di Di  **/
        $di = $this->getMetadata()->getService('di');
        $di->set(new Scenario($this));
        } catch (InjectionException $exception) {
            $this->_before();
            return;
        }
        // auto-inject $tester property
        if (($this->getMetadata()->getCurrent('actor')) && ($property = lcfirst(Configuration::config()['actor_suffix']))) {
            $this->$property = $di->instantiate($this->getMetadata()->getCurrent('actor'));
        }

        // Auto inject into the _inject method
        $di->injectDependencies($this); // injecting dependencies
        $this->_before();
    }

    /**
     * @Override
     */
    protected function _before()
    {
    }

    protected function _tearDown()
    {
        $this->_after();
    }

    /**
     * @Override
     */
    protected function _after()
    {
    }

    /**
     * @param $module
     * @return \Codeception\Module
     * @throws ModuleException
     */
    public function getModule($module)
    {
        $modules = $this->getMetadata()->getCurrent('modules');
        if (!isset($modules[$module])) {
            throw new ModuleException($module, "Module can't be accessed");
        }
        return $modules[$module];
    }

    /**
     * Returns current values
     */
    public function getCurrent($current)
    {
        return $this->getMetadata()->getCurrent($current);
    }

    /**
     * @return array
     */
    public function getReportFields()
    {
        return [
            'name'    => $this->getName(),
            'class'   => get_class($this),
            'file'    => $this->getMetadata()->getFilename()
        ];
    }

    public function fetchDependencies()
    {
        $names = [];
        foreach ($this->getMetadata()->getDependencies() as $required) {
            if ((strpos($required, ':') === false) and method_exists($this, $required)) {
                $required = get_class($this) . ":$required";
            }
            $names[] = $required;
        }
        return $names;
    }

    /**
     * Reset PHPUnit's dependencies
     * @return bool
     */
    public function handleDependencies()
    {
        $dependencies = $this->fetchDependencies();
        if (empty($dependencies)) {
            return true;
        }
        $passed = $this->getTestResultObject()->passed();
        $dependencyInput = [];

        foreach ($dependencies as $dependency) {
            $dependency = str_replace(':', '::', $dependency); // Codeception => PHPUnit format
            if (strpos($dependency, '::') === false) {         // check it is method of same class
                $dependency = get_class($this) . '::' . $dependency;
            }
            if (isset($passed[$dependency])) {
                $dependencyInput[] = $passed[$dependency]['result'];
            } else {
                $dependencyInput[] = null;
            }
        }
        $this->setDependencyInput($dependencyInput);
        return true;
    }
}
