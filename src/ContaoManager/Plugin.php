<?php

namespace Berecont\ContaoYoutubePreview\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Berecont\ContaoYoutubePreview\BerecontContaoYoutubePreviewBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(BerecontContaoYoutubePreviewBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}