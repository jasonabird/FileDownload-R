<?php

class FileDownloadPlugin {

    public $modx;
    public $configs;
    public $fileDownload;
    public $event;
    public $events = array();
    public $placeholders = array();
    public $errors = array();

    public function __construct(FileDownload $fileDownload) {
        $this->modx = & $fileDownload->modx;
        $this->configs = $fileDownload->configs;
        $this->fileDownload = $fileDownload;
        $this->allEvents = include $this->configs['basePath'] . 'plugins/filedownloadplugin.events.php';
    }

    public function addPlaceholder($key, $value) {
        $this->placeholders[$key] = $value;
    }

    public function addPlaceholders(array $phs) {
        foreach ($phs as $key => $value) {
            $this->addPlaceholder($key, $value);
        }
    }

    /**
     * @todo redirect page after downloading
     * @param type $url
     */
    public function redirect($url) {

    }

    /**
     * Arrange the $scriptProperties, plugins and events correlation, then fill
     * the $events property
     * @return void $this->events
     */
    public function preparePlugins() {
        $jPlugins = json_decode($this->configs['plugins'], 1);
        foreach ($jPlugins as $v) {
            $this->events[$v['event']][] = $v;
        }
        foreach ($this->allEvents as $i => $event) {
            if (isset($this->events[$i]))
                $this->allEvents[$i] = $this->events[$i];
        }
    }

    public function getAllEvents() {
        return $this->allEvents;
    }

    public function getEvents() {
        return $this->events;
    }

    public function getEvent() {
        return $this->event;
    }

    /**
     * Get all plugins, with the strict option if it is enabled by the snippet
     * @param   string          $eventName  name of the event
     * @param   boolean         $toString   return the results as string instead
     * @return  boolean|array   FALSE | plugin's output array
     */
    public function getPlugins($eventName, $toString=FALSE) {
        $this->event = $eventName;
        $output = array();
        foreach ($this->events[$eventName] as $plugin) {
            $loaded = $this->_loadPlugin($plugin);
            if (!$loaded) {
                if (!empty($plugin['strict'])) {
                    return FALSE;
                } else {
                    continue;
                }
            } else {
                $output[] = $loaded;
            }
        }
        if ($toString) {
            $output = @implode("\n", $output);
        }
        return $output;
    }

    /**
     * Set custom property for the plugin in the run time
     * @param   string  $key    key
     * @param   string  $val    value
     * @return  void
     */
    public function setProperty($key, $val) {
        $this->configs = array_merge($this->configs, array($key => $val));
    }

    /**
     * Set custom properties for the plugin in the run time in an array of
     * key => value pairings
     * @param   array   $array  array of the properties
     * @return  void
     */
    public function setProperties($array=array()) {
        if (is_array($array)) {
            foreach ($array as $key => $val) {
                $this->setProperty($key, $val);
            }
        }
    }

    public function getProperty($key) {
        return $this->configs[$key];
    }

    public function getProperties() {
        return $this->configs;
    }

    private function _loadPlugin ($plugin) {
        $pluginName = $plugin['name'];
        $success = FALSE;
        if ($snippet = $this->modx->getObject('modSnippet',array('name' => $pluginName))) {
            /* custom snippet plugin */
            $properties = $this->configs;
            $properties['fileDownload'] =& $this->fileDownload;
            $properties['plugin'] =& $this;
            $properties['errors'] =& $this->errors;
            $success = $snippet->process($properties);
        } else {
            $plugin =& $this;
            $modx = $this->modx;
            $fileDownload = $this->fileDownload;

            /* search for a file-based plugin */
            $this->modx->parser->processElementTags('',$pluginName,true,true);
            if (file_exists($pluginName)) {
                $success = $this->_loadFileBasedPlugin($pluginName);
            } else {
                /* no plugin found */
                $this->modx->log(modX::LOG_LEVEL_ERROR,'[FileDownload] Could not find plugin "'.$pluginName.'".');
                $success = FALSE;
            }
        }

        return $success;
    }

    /**
     * Attempt to load a file-based plugin given a name
     * @param string $path The absolute path of the plugin file
     * @param array $customProperties An array of custom properties to run with the plugin
     * @return boolean True if the plugin succeeded
     */
    private function _loadFileBasedPlugin($path) {
        $scriptProperties = $this->configs;
        $fileDownload =& $this->fileDownload;
        $plugin =& $this;
        $errors =& $this->errors;
        $modx =& $this->modx;
        $success = false;
        try {
            $success = include $path;
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[FileDownload] '.$e->getMessage());
        }
        return $success;
    }
}