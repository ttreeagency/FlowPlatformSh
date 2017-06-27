<?php

namespace Ttree\FlowPlatformSh;

use M1\Env\Parser;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Utility\Arrays;

class Package extends BasePackage
{
    /**
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $relationships = trim(getenv('PLATFORM_RELATIONSHIPS'));
        if (!$relationships) {
            return;
        }
        $relationships = json_decode(base64_decode($relationships), true);
        if ($relationships === null) {
            return;
        }

        $file = \FLOW_PATH_ROOT . '.platform.env';
        if (!file_exists($file)) {
            return;
        }
        foreach (Parser::parse(file_get_contents($file)) as $name => $path) {
            putenv(sprintf('%s=%s', trim($name), Arrays::getValueByPath($relationships, trim($path))));
        }
    }
}
