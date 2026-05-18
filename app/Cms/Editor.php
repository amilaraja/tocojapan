<?php

namespace App\Cms;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;

/**
 * Shared rich-content editor for CMS page templates — a TinyMCE editor
 * with image upload to the public disk. TinyMCE treats every <img> as a
 * native, selectable element, so embedded images can be clicked and
 * deleted directly (unlike the previous lightweight editor).
 */
class Editor
{
    public static function make(string $name, string $label): TinyEditor
    {
        return TinyEditor::make($name)
            ->label($label)
            ->profile('default')
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsVisibility('public')
            ->columnSpanFull();
    }
}
