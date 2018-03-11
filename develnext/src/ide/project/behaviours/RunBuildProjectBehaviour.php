<?php
namespace ide\project\behaviours;

use ide\build\AntOneJarBuildType;
use ide\build\SetupWindowsApplicationBuildType;
use ide\build\WindowsApplicationBuildType;
use ide\commands\BuildProjectCommand;
use ide\commands\ExecuteProjectCommand;
use ide\Ide;
use ide\project\AbstractProjectBehaviour;
use ide\utils\FileUtils;
use php\lib\arr;
use php\lib\fs;
use php\lib\str;

/**
 * Class RunBuildProjectBehaviour
 * @package ide\project\behaviours
 */
class RunBuildProjectBehaviour extends AbstractProjectBehaviour
{
    public function createExecuteCommand(): ExecuteProjectCommand
    {
        return new ExecuteProjectCommand($this);
    }

    public function createBuildCommand(): BuildProjectCommand
    {
        $buildProjectCommand = new BuildProjectCommand();
        $buildProjectCommand->register(new WindowsApplicationBuildType());
        $buildProjectCommand->register(new SetupWindowsApplicationBuildType());
        //$buildProjectCommand->register(new OneJarBuildType());

        $buildType = new AntOneJarBuildType();
        $buildType->setMainClass('org.develnext.jphp.ext.javafx.FXLauncher');
        $buildProjectCommand->register($buildType);

        return $buildProjectCommand;
    }

    /**
     * ...
     */
    public function inject()
    {
        Ide::get()->registerCommand($this->createExecuteCommand());
        Ide::get()->registerCommand($this->createBuildCommand());
    }

    /**
     * @return array
     */
    public function getSourceDirectories()
    {
        return ['src_generated/', 'src'];
    }

    /**
     * @return array
     */
    public function getRepositoryDirectories()
    {
        if ($gradle = GradleProjectBehaviour::get()) {
            $result = $gradle->getConfig()->getDirectoryRepositories();

            foreach ($gradle->getConfig()->getDependencies() as list($group, $artifactId, $version)) {
                if (!$group && !$version && str::startsWith($artifactId, 'dir:')) {
                    $result[] = str::sub($artifactId, 4);
                }
            }

            return $result;
        }

        return [];
    }

    /**
     * @param array $types extensions
     * @return array
     */
    public function getProfileModules(array $types)
    {
        $result = [];

        if ($project = $this->project) {
            foreach ($project->getModules() as $module) {
                switch ($module->getType()) {
                    default:
                        if (fs::exists($module->getId())) {
                            if (fs::isFile($module->getId())) {
                                $result[] = fs::abs($module->getId());
                            } elseif (fs::isDir($module->getId())) {
                                fs::scan($module->getId(), function ($filename) use ($module, &$result) {
                                    if (fs::name($filename) == fs::nameNoExt($module->getId())) {
                                        return;
                                    }

                                    $result[] = fs::abs($filename);
                                }, 1);
                            }
                        }

                        break;
                }
            }
        }

        $new = [];

        foreach ($result as $one) {
            if (arr::has($types, fs::ext($one))) {
                $new[] = FileUtils::adaptName($one);
            }
        }

        return $new;
    }

    /**
     * see PRIORITY_* constants
     * @return int
     */
    public function getPriority()
    {
        return self::PRIORITY_COMPONENT;
    }
}