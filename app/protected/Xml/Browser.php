<?php
/**
 * Browser.php
 *
 * @category  xmlExplorer
 * @package   Tehem\Xml
 * @author    Tehem <root@tehem.net>
 *
 * @since     21/04/15 11:31
 * @copyright 2016 Tehem.net - All Rights Reserved
 */

namespace Tehem\Xml;

/**
 * Class Browser
 *
 * @package Windataco\Xml
 */
class Browser
{
    /**
     * Get file count in a directory
     *
     * @param string $directory
     *
     * @return int file count in directory
     */
    public static function getFileCount($directory)
    {
        $filecount = 0;
        $files     = glob($directory . "/*.xml");
        if ($files) {
            $filecount = count($files);
        }
        $files = glob($directory . "/*.XML");
        if ($files) {
            $filecount += count($files);
        }

        return $filecount;
    }

    /**
     * Get files in a directory
     *
     * @param string $directory
     *
     * @return array all files in directory
     */
    public static function getFiles($directory)
    {
        $files = array();
        if ($handle = opendir($directory)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $files[basename($entry)] = $entry;
                }
            }

            closedir($handle);
        }

        return $files;
    }
}
