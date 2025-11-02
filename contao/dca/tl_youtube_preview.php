<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;
use Contao\StringUtil;
use Contao\FilesModel;

$GLOBALS['TL_DCA']['tl_youtube_preview'] = [

    // Konfiguration der Tabelle
    'config' => [
        'dataContainer' => DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ],
        ],
        // ⬇️ HIER MUSS DER CALLBACK STEHEN
        'onsubmit_callback' => [
            [\Berecont\ContaoYoutubePreview\EventListener\Dca\TlYoutubePreviewListener::class, 'onSubmit'],
        ],
    ],

    // Listenansicht im Backend
    'list' => [
        'sorting' => [
            'mode'        => DataContainer::MODE_SORTABLE,
            'fields'      => ['tstamp DESC'],
            'flag'        => DataContainer::SORT_DAY_DESC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields'         => ['youtube_id', 'generatedImage'],
            'showColumns'    => false,
            'label_callback' => static function (array $row, string $label, DataContainer $dc, array $args) {
                $yt = $row['youtube_id'] ?? '';

                $thumbHtml = '';
                if (!empty($row['generatedImage'])) {
                    try {
                        $uuid  = StringUtil::binToUuid($row['generatedImage']);
                        if ($file = FilesModel::findByUuid($uuid)) {
                            $src = \System::getContainer()->get('contao.image.picture_factory')
                                ->create($file->path, (new \Contao\Image\ResizeConfiguration())->setWidth(100)->setHeight(60)->setMode('box'))
                                ->getImg()['src'] ?? $file->path;

                            $thumbHtml = sprintf(
                                '<img src="%s" alt="" style="height:60px; vertical-align:middle; margin-right:10px; border-radius:4px;">',
                                $src
                            );
                        }
                    } catch (\Throwable $e) {
                        // kein Thumb
                    }
                }

                return $thumbHtml . '<strong>' . htmlspecialchars($yt) . '</strong>';
            },
        ],
        'global_operations' => [
            'all' => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'edit'   => ['href' => 'act=edit',   'icon' => 'edit.svg'],
            'copy'   => ['href' => 'act=copy',   'icon' => 'copy.svg'],
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? 'Do you really want to delete record ID %s?') . '\'))return false;Backend.getScrollOffset()"',
            ],
            'show'   => ['href' => 'act=show',   'icon' => 'show.svg'],
        ],
    ],

    // Paletten & Felder bleiben wie bei dir
    'palettes' => [
        '__selector__' => [],
        //'default'      => '{title_legend},youtube_id;{preview_legend},targetFolder,generatedImage;',
        'default'      => '{title_legend},youtube_id;{preview_legend},targetFolder,generatedPreview;',
    ],

    
    // Felder
    'fields' => [
        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
		'tstamp' => [
			'filter' => true,
			'flag' => DataContainer::SORT_DAY_DESC,
			'eval' => ['rgxp' => 'datim'],
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'rootPage' => [
			'filter' => true,
			'search' => true,
			'foreignKey' => 'tl_page.title',
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'ip' => [
			'search' => true,
			'sql' => "varchar(64) NOT NULL default ''",
		],
		'url' => [
			'search' => true,
			'sql' => 'text NULL',
		],
		'referrer' => [
			'search' => true,
			'sql' => 'text NULL',
		],
		'agent' => [
			'search' => true,
			'sql' => 'text NULL',
		],        
        'youtube_id' => [
            'label'     => ['YouTube ID', 'Die Video-ID von YouTube (z. B. dQw4w9WgXcQ)'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => [
                'maxlength'  => 64,
                'tl_class'   => 'w50',
                'mandatory'  => true,
            ],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'targetFolder' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_youtube_preview']['targetFolder'],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => [
                'mandatory' => true,
                'fieldType' => 'radio',
                'files'     => false,
                'tl_class'  => 'w50 clr',
            ],
            'sql'       => "binary(16) NULL",
        ],
    ],
];
