<?php

/**
 * @author Linus Norton <linusnorton@gmail.com>
 * @package core
 *
 * The factory class is in charge of creating new objects.
 *
 * It dynamically includes the files only when they are needed to instanciate
 * an object.
 *
 * The file name must be the same as the class name (plus .php) as you can see
 * this class is called Factory and the file is Factory.php
 */
 class Factory {
    private static $objects = array();
    private static $loadedPackages = array();
    private static $tmp;
    private static $fileTypes = array(".php", ".php4", ".php5", ".mphp", ".phpm"); //allowed file types
    private static $autoloader;

    /** include the class do not create the object */
    public static function includeFile($className) {
        //if we have a mapping for the object
        if (isset(self::$objects[$className])) {
            try {
                return include_once(self::$objects[$className]);
            }
            catch (FrameEx $ex) { /*drop below and return false */ }
        }

        return false;
    }

    /**
     * Piggy back the namespace autoloader
     * 
     * @param model\core\Autloader $loader
     */
    public static function attachAutoloader(model\core\Autoloader $loader) {
        self::$autoloader = $loader;
    }

    /**
     * Adds an instructions to load a class
     *
     * @param String $name key to use for the object
     * @param String $classPath path to the file defining the class
     */
    public static function add($name, $classPath) {
        self::$objects[$name] = $classPath;
    }

    public static function rebuild() {
        self::rebuildRootDirectory(dirname(__FILE__)."/../../");
        self::rebuildRootDirectory(APP_DIR);

        foreach (self::$loadedPackages as $package) {
            self::rebuildRootDirectory($package.DIRECTORY_SEPARATOR);
        }
    }

    /**
     * Goes through every directory in the root looks for a init.php
     * if so it scans subdirectories for .php files with classes inside
     */
    private static function rebuildRootDirectory($root) {
        // Open the root
        if (!is_dir($root)) {
            return;
        }

        //open the root
        if ($dh = opendir($root)) {
            //loop over the files
            while (($file = readdir($dh)) !== false) {
                //if there is an init.php it MUST be part of the framework
                if (file_exists($root.$file.DIRECTORY_SEPARATOR."init.php")) {
                    //rebuild the directories classes.php file
                    Factory::rebuildDirectory($root.$file.DIRECTORY_SEPARATOR);
                }
                else if (ucfirst($file) == "Zend") {
                    //go into "zend mode"
                    Factory::rebuildDirectory($root.$file.DIRECTORY_SEPARATOR, true);
                }
            }
            closedir($dh);
        }

    }

    /**
     * Rebuilds a particular directories class mapping
     */
    public static function rebuildDirectory($dir, $zendMode = false) {
        $classes = array();
        Factory::getClassesInDirectory($dir, $classes, $zendMode);

        $classesFileName = self::$tmp.str_replace(DIRECTORY_SEPARATOR, "_", realpath($dir)).".classes.php";
        Factory::buildClassFile($classesFileName, $classes);
        include($classesFileName);
    }

    /**
     * Gets all the classes out of a directory. Assumes that any
     * php file starting with a capital has a class inside with the
     * same name
     *
     * @param $dir string directory to look in
     * @param &$classes array stores the classes that are found
     */
    private static function getClassesInDirectory($dir, &$classes, $zendMode = false) {
        if (!is_dir($dir) || file_exists($dir."/.ignore")) {
            return;
        }

        //lets get into that directory
        if ($dh = opendir($dir)) {
            //for each file
            while (($file = readdir($dh)) !== false) {
                if ($file == "." || $file == ".."  || $file == ".svn" || $file == ".git" || $file == "vendor") {
                    continue;
                }

                $ext = ".".pathinfo($dir.$file , PATHINFO_EXTENSION);

                //if im a directory
                if (is_dir($dir.$file)) {
                    //lets get recursive and add all the classes in this dir
                    Factory::getClassesInDirectory($dir.$file.DIRECTORY_SEPARATOR, $classes, $zendMode);
                }
                //if im an acceptable file and the first char is upper case(!!!!)
                else if (in_array($ext, self::$fileTypes) && ctype_upper($file[0])) {
                    $class = str_replace($ext, "", $file);

                    if ($zendMode) {
                        $class = str_replace(DIRECTORY_SEPARATOR, "_", $class);
                    }
                    $classes[$class] = $dir.$file;
                }
            }
            closedir($dh);
        }
    }

    /**
     * builds the class file
     *
     * @param $filename string place to put the classes mapping
     * @param $classes array array of classes and files
     */
    private static function buildClassFile($filename, array $classes) {
        $contents = "<?php \n\n//This file is automatically generated and should not be changed\n";
        $contents .= "//It contains the class/file map for the autoloading Factory object\n";

        foreach ($classes as $class => $file) {
            $contents .= "Factory::add('{$class}','".realpath($file)."');\n";
        }

        try {
            file_put_contents($filename, $contents, LOCK_EX);
        }
        catch (FrameEx $ex) {
            trigger_error("Could not write {$filename}, please check permissions.");
        }
        
        //make the class map globally available
        try {
            chmod($filename, 0777);
        }
        catch (FrameEx $ex) { /* we tried */ }
    }

    /**
     * include the framework files
     */
    public static function init() {
        self::$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR;
        spl_autoload_register("Factory::autoload");
        Factory::$objects["Annotation"] = dirname(__FILE__)."/../util/addendum/annotations.php";
        
        //include the paths to the classes for the framework
        try {
            $baseDir = ('@php_dir@' == '@'.'php_dir@') ? dirname(__FILE__).'/../../' : '@php_dir@'.'/xframe/';
            $classesFilename = str_replace("/","_", realpath($baseDir)).".classes.php";
            @include(self::$tmp.$classesFilename);
        }
        catch (FrameEx $ex) {
            Factory::rebuild();
        }
    }

    /**
     * Include the class mapping and run the init script of the given package
     * @param string $package
     */
    public static function boot($package) {
        $tmpPath = self::$tmp.str_replace(DIRECTORY_SEPARATOR,"_",realpath($package));

        //load the class mapping
        try {
            include($tmpPath.".classes.php");
        }
        catch (FrameEx  $ex) { 
            self::rebuildDirectory($package."/");               
        }

        //boot
        try {
            include_once(realpath($package.DIRECTORY_SEPARATOR."init.php"));
        }
        catch (FrameEx $ex) {
            $ex->setMessage("Unable to boot package: {$package}: ".$ex->getMessage());
            throw $ex;
        }

        //load the class mapping
        try {
            include($tmpPath.".request-map.php");
        }
        catch (FrameEx  $ex) {
            RequestMapGenerator::build($package);
            include($tmpPath.".request-map.php");
        }

        if (self::$autoloader) {
            self::$autoloader->addPath(realpath($package.DIRECTORY_SEPARATOR));
        }

        self::$loadedPackages[] = $package;
    }

    /**
     * Returns an array of all packages currently loaded
     * @return array
     */
    public static function getLoadedPackages() {
        return self::$loadedPackages;
    }

    /**
     * If new <object> is called this function calls the Factory to include the file
     *
     * @param String $className
     */
    public static function autoload($className) {
        //if the factory does not have the class
        if (!Factory::includeFile($className)) {
            //rebuild the class/file mapping
            Factory::rebuild();
            //try to see if we have it now
            Factory::includeFile($className);
        }
    }
}

