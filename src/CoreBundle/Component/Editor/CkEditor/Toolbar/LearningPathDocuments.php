<?php

declare(strict_types=1);

/* For licensing terms, see /license.txt */

namespace Chamilo\CoreBundle\Component\Editor\CkEditor\Toolbar;

/**
 * LearningPathDocuments toolbar configuration.
 */
class LearningPathDocuments extends Basic
{
    /**
     * Get the toolbar config.
     *
     * @return array
     */
    public function getConfig()
    {
        if ('true' !== api_get_setting('more_buttons_maximized_mode')) {
            $config['toolbar'] = $this->getNormalToolbar();
        } else {
            $config['toolbar_minToolbar'] = $this->getMinimizedToolbar();
            $config['toolbar_maxToolbar'] = $this->getMaximizedToolbar();
        }

        $config['fullPage'] = true;

        return $config;
    }

    /**
     * Get the toolbar configuration when CKEditor is maximized.
     *
     * @return array
     */
    protected function getMaximizedToolbar()
    {
        return [
            ['NewPage', 'Templates', '-', 'Preview', 'Print'],
            ['Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord'],
            ['Undo', 'Redo', '-', 'SelectAll', 'Find', '-', 'RemoveFormat'],
            ['Link', 'Unlink', 'Anchor', 'Glossary'],
            [
                'Image',
                'Mapping',
                'Video',
                'Oembed',
                'Youtube',
                'Flash',
                'Audio',
                'leaflet',
                'Smiley',
                'SpecialChar',
                'Asciimath',
                'Asciisvg',
            ],
            '/',
            ['Table', '-', 'CreateDiv'],
            ['BulletedList', 'NumberedList', 'HorizontalRule', '-', 'Outdent', 'Indent', 'Blockquote'],
            ['JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'],
            ['Bold', 'Italic', 'Underline', 'Strike', '-', 'Subscript', 'Superscript', '-', 'TextColor', 'BGColor'],
            ['true' === api_get_setting('allow_spellcheck') ? 'Scayt' : ''],
            ['Styles', 'Format', 'Font', 'FontSize'],
            ['PageBreak', 'ShowBlocks', 'Source'],
            ['Toolbarswitch'],
        ];
    }

    /**
     * Get the default toolbar configuration when the setting more_buttons_maximized_mode is false.
     *
     * @return array
     */
    protected function getNormalToolbar()
    {
        return [
            ['PasteFromWord', '-', 'Undo', 'Redo'],
            ['Link', 'Unlink', 'Anchor', 'Glossary'],
            [
                'Image',
                'Video',
                'Flash',
                'Oembed',
                'Youtube',
                'Audio',
                'Asciimath',
                'Asciisvg',
                'Table',
                'SpecialChar',
            ],
            [
                'Outdent',
                'Indent',
                'TextColor',
                'BGColor',
                '-',
                'NumberedList',
                'BulletedList',
                'JustifyLeft',
                'JustifyCenter',
                'JustifyRight',
            ],
            '/',
            ['Styles', 'Format', 'Font', 'FontSize'],
            ['Bold', 'Italic', 'Underline', '-', 'Source'],
        ];
    }

    /**
     * Get the toolbar configuration when CKEditor is minimized.
     *
     * @return array
     */
    protected function getMinimizedToolbar()
    {
        return [
            $this->getNewPageBlock(),
            ['Undo', 'Redo'],
            ['Link', 'Image', 'Video', 'Youtube', 'Flash', 'Audio', 'Table', 'Asciimath', 'Asciisvg'],
            ['BulletedList', 'NumberedList', 'HorizontalRule'],
            ['JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'],
            ['Toolbarswitch'],
            ['Styles', 'Format', 'Font', 'FontSize', 'Bold', 'Italic', 'Underline', 'TextColor', 'BGColor'],
            ['ShowBlocks', 'Source'],
        ];
    }
}
