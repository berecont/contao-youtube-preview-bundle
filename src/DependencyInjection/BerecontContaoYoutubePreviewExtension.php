<?php

declare(strict_types=1);

namespace Berecont\ContaoYoutubePreview\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class BerecontContaoYoutubePreviewExtension extends Extension
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../config')
        );

        $loader->load('services.yaml');

        $GLOBALS['TL_CSS'][] = 'bundles/contao-youtube-preview-bundle/backend.css|static';
    }
}
