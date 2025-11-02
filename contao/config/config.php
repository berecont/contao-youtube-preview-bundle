<?php

use Berecont\ContaoYoutubePreview\Model\ContaoYoutubePreviewModel;

$GLOBALS['BE_MOD']['youtube_preview_image']['youtube_preview'] = [
    'tables' => ['tl_youtube_preview'],
];

$GLOBALS['TL_MODELS']['tl_youtube_preview'] = ContaoYoutubePreviewModel::class;