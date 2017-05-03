<?php

namespace bl\lessphp;

use Yii;

/**
 * AssetConverter supports conversion of less script format into CSS script.
 *
 * It is used by [[AssetManager]] to convert files after they have been published.
 *
 * @author cakebake (Jens A.)
 * @since 2.0
 * @see https://github.com/oyejorge/less.php
 */
class AssetConverter extends \yii\web\AssetConverter
{
    const INPUT_EXT = 'less';

    const OUTPUT_EXT = 'css';

    /**
    * @var bool You can tell less.php to remove comments and whitespace to generate minimized css files.
    */
    public $compress = false;

    /**
     * @var bool
     */
    public $forceParse = false;

    /**
    * @var bool less.php will save serialized parser data for each .less file. Faster, but more memory-intense.
    */
    public $useCache = false;

    /**
    * @var string|null is passed to the SetCacheDir() method. By default "cakebake\lessphp\runtime" is used.
    */
    public $cacheDir = null;

    /**
    * @var bool Filename suffix to avoid the browser cache and force recompiling by configuration changes
    */
    public $cacheSuffix = false;

    /**
     * Converts a given LESS assets file into a CSS
     *
     * @param string $asset the asset file path, relative to $basePath
     * @param string $basePath the directory the $asset is relative to.
     * @return string the converted asset file path, relative to $basePath.
     */
    public function convert($asset, $basePath)
    {
        if (($dotPos = strrpos($asset, '.')) === false)
            return $asset;

        if (($ext = substr($asset, $dotPos + 1)) !== self::INPUT_EXT)
            return parent::convert($asset, $basePath);

        $assetFilemtime = @filemtime("$basePath/$asset");
        $result = $this->buildResult($asset, $dotPos, ($this->cacheSuffix === true) ? $assetFilemtime : null);
        $resultFilemtime = @filemtime("$basePath/$result");

        if ($resultFilemtime < $assetFilemtime || $this->forceParse === true) {
            $this->parseLess($basePath, $asset, $result);
        }

        return $result;
    }

    /**
    * Builds the result file name
    *
    * @param string $asset the asset file path, relative to $basePath
    * @param int $dotPos the strrpos position of filename-extension dot
    * @param mixed $resultSuffix Suffix result css filename
    * @return string the converted asset file path, relative to $basePath.
    */
    protected function buildResult($asset, $dotPos = null, $resultSuffix = null)
    {
        if ($dotPos === null) {
            if (($dotPos = strrpos($asset, '.')) === false) {
                return $asset;
            }
        }

        $divider = ($this->compress === true) ? '-m' : '-';
        $suffix = ($resultSuffix !== null) ? $divider . (string)$resultSuffix . '.' . self::OUTPUT_EXT : '.' . self::OUTPUT_EXT;

        return substr($asset, 0, $dotPos) . $suffix;
    }

    /**
     * Parsing Less File
     *
     * @param string $basePath asset base path and command working directory
     * @param string $asset the name of the asset file
     * @param string $result the name of the file to be generated by the converter command
     * @return boolean true on success, false on failure. Failures will be logged.
     * @throws Less_Exception_Parser when the command fails
     */
    protected function parseLess($basePath, $asset, $result)
    {
        $parser = new \Less_Parser([
            'compress' => ($this->compress === true) ? true : false,
            'cache_dir' => ($this->useCache === true) ? ($this->cacheDir !== null && is_dir($this->cacheDir)) ? $this->cacheDir : __DIR__ . DIRECTORY_SEPARATOR . 'cache' : false,
        ]);

        $parser->parseFile($basePath . DIRECTORY_SEPARATOR . $asset);

        if ((!$css = $parser->getCss()) || empty($css))
            return false;

        Yii::trace("Converted $asset into $result", __METHOD__);

        return file_put_contents($basePath . DIRECTORY_SEPARATOR . $result, $css, LOCK_EX);
    }
}
